<?php

namespace App\Services;

use App\Models\StatementLedgerBundle;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StatementReconciliationService
{
    private const CHAIN_EPSILON = 0.02;

    public function __construct(
        private StatementMatchKeyService $matchKeys,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions  Rows from StatementParserService::parse
     * @return array{
     *     period: array{start: string|null, end: string|null},
     *     summary: array<string, mixed>,
     *     transactions: array<int, array<string, mixed>>
     * }
     */
    public function analyze(array $parsedTransactions, ?int $accountId): array
    {
        $period = $this->derivePeriod($parsedTransactions);
        $skewDays = max(0, (int) config('statement.reconcile_date_skew_days', 3));
        $thinProximityMaxDays = config('statement.reconcile_thin_proximity_max_days');
        $thinProximityMaxDays = $thinProximityMaxDays !== null ? max(0, (int) $thinProximityMaxDays) : null;
        $debugReconcile = (bool) config('statement.reconcile_debug', false);

        $expandedStart = null;
        $expandedEnd = null;
        if ($period['start'] && $period['end']) {
            $expandedStart = Carbon::parse($period['start'])->subDays($skewDays)->format('Y-m-d');
            $expandedEnd = Carbon::parse($period['end'])->addDays($skewDays)->format('Y-m-d');
        }

        $balanceSummary = $this->computeBalanceSummary(
            $parsedTransactions,
            $accountId,
            $expandedStart,
            $expandedEnd,
        );

        if ($accountId === null || $parsedTransactions === []) {
            return [
                'period' => $period,
                'summary' => [
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'missing_in_db' => 0,
                    'matched_complete' => 0,
                    'matched_needs_enrichment' => 0,
                    'balance' => $balanceSummary,
                ],
                'transactions' => $this->annotateUnscoped($parsedTransactions),
            ];
        }

        $dbExpanded = Transaction::query()
            ->where('account_id', $accountId)
            ->with(['category.parent'])
            ->when($expandedStart && $expandedEnd, function ($q) use ($expandedStart, $expandedEnd): void {
                $q->whereDate('transaction_date', '>=', $expandedStart)
                    ->whereDate('transaction_date', '<=', $expandedEnd);
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        /** @var array<string, Transaction> $firstByKey */
        $firstByKey = [];
        /** @var array<string, array<int, Transaction>> $thinBucketsMulti */
        $thinBucketsMulti = [];
        /** @var array<string, array<int, Transaction>> $secondaryBuckets */
        $secondaryBuckets = [];
        /** @var array<string, array<int, Transaction>> $thinAmountBuckets */
        $thinAmountBuckets = [];

        foreach ($dbExpanded as $txn) {
            $fk = $this->matchKeys->matchKeyFromDbTransaction($txn);
            if (! isset($firstByKey[$fk])) {
                $firstByKey[$fk] = $txn;
            }

            $descDb = (string) ($txn->description ?? '');
            $amt = (float) $txn->amount;
            $secKey = $this->matchKeys->matchKeyAmountNormDesc($amt, $descDb);
            $secondaryBuckets[$secKey][] = $txn;

            $dateDb = $txn->transaction_date instanceof Carbon
                ? $txn->transaction_date->format('Y-m-d')
                : Carbon::parse((string) $txn->transaction_date)->format('Y-m-d');

            if ($this->matchKeys->isDescriptionThin($descDb)) {
                $bk = $this->thinDbBucketKey($dateDb, $amt);
                $thinBucketsMulti[$bk][] = $txn;
                $amtKey = $this->matchKeys->thinAmountOnlyBucketKey($amt);
                $thinAmountBuckets[$amtKey][] = $txn;
            }
        }

        $statementNet = $this->statementNetDelta($parsedTransactions);
        $narrowDb = $this->narrowDbTransactions($dbExpanded, $period);
        $dbNet = $this->dbNetDelta($narrowDb);
        $balanceSummary['net_delta_statement_minus_db'] = round($statementNet - $dbNet, 2);

        /** @var array<int, true> $usedLedgerIds */
        $usedLedgerIds = [];

        $bundleAssignments = $this->resolveBundleAssignments($parsedTransactions, $accountId);
        foreach ($bundleAssignments as $assignment) {
            $usedLedgerIds[$assignment['txn']->id] = true;
        }

        $counts = ['missing_in_db' => 0, 'matched_complete' => 0, 'matched_needs_enrichment' => 0];

        $n = count($parsedTransactions);
        /** @var array<int, Transaction|null> $matches */
        $matches = array_fill(0, $n, null);
        /** @var array<int, array<string, mixed>> */
        $matchMeta = array_fill(0, $n, [
            'matched_via_thin_bucket' => false,
            'matched_via_thin_amount' => false,
            'matched_via_bundle' => false,
            'bundle_id' => null,
            'miss_reason' => null,
        ]);

        foreach ($bundleAssignments as $idx => $assignment) {
            $matches[(int) $idx] = $assignment['txn'];
            $matchMeta[(int) $idx]['matched_via_bundle'] = true;
            $matchMeta[(int) $idx]['bundle_id'] = $assignment['bundle_id'];
            $matchMeta[(int) $idx]['miss_reason'] = 'matched_bundle';
        }

        $sortedIndices = $this->sortedStatementIndices($parsedTransactions);

        foreach ($sortedIndices as $idx) {
            if ($matches[$idx] !== null) {
                continue;
            }

            $row = $parsedTransactions[$idx];
            $desc = (string) ($row['description'] ?? '');
            $date = (string) ($row['date'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);
            $stmtType = (string) ($row['type'] ?? '');
            $keyStmt = $this->matchKeys->matchKey($date, $amount, $desc);

            $match = null;
            $thinBucket = false;
            $thinAmount = false;
            $missReason = 'none';

            $primaryCand = $firstByKey[$keyStmt] ?? null;
            if ($primaryCand !== null && ! isset($usedLedgerIds[$primaryCand->id])) {
                $match = $primaryCand;
                $missReason = 'matched_primary';
            }

            if ($match === null) {
                $bk = $this->thinDbBucketKey($date, $amount);
                $list = $thinBucketsMulti[$bk] ?? [];
                $picked = $this->pickNearestUnusedThin($list, $date, $stmtType, $usedLedgerIds, $thinProximityMaxDays);
                if ($picked !== null) {
                    $match = $picked;
                    $thinBucket = true;
                    $missReason = 'matched_thin_date_bucket';
                }
            }

            if ($match === null) {
                $secStmtKey = $this->matchKeys->matchKeyAmountNormDesc($amount, $desc);
                $secList = $secondaryBuckets[$secStmtKey] ?? [];
                if (count($secList) === 1) {
                    if (! isset($usedLedgerIds[$secList[0]->id])) {
                        $match = $secList[0];
                        $missReason = 'matched_secondary_unique';
                    } else {
                        $missReason = 'secondary_candidate_already_used';
                    }
                } elseif (count($secList) > 1) {
                    $missReason = 'secondary_ambiguous';
                } else {
                    $missReason = 'secondary_miss';
                }
            }

            if ($match === null) {
                $atk = $this->matchKeys->thinAmountOnlyBucketKey($amount);
                $amtList = $thinAmountBuckets[$atk] ?? [];
                $picked = $this->pickNearestUnusedThin($amtList, $date, $stmtType, $usedLedgerIds, $thinProximityMaxDays);
                if ($picked !== null) {
                    $match = $picked;
                    $thinAmount = true;
                    $missReason = 'matched_thin_amount_proximity';
                } elseif ($amtList !== []) {
                    $missReason = 'thin_amount_filtered_or_used';
                }
            }

            if ($match === null) {
                $matchMeta[$idx]['miss_reason'] = $missReason === 'none' ? 'no_candidates' : $missReason;

                continue;
            }

            $matchMeta[$idx]['matched_via_thin_bucket'] = $thinBucket;
            $matchMeta[$idx]['matched_via_thin_amount'] = $thinAmount;
            $matchMeta[$idx]['miss_reason'] = $missReason;
            $matches[$idx] = $match;
            $usedLedgerIds[$match->id] = true;
        }

        $out = [];
        for ($idx = 0; $idx < $n; $idx++) {
            $row = $parsedTransactions[$idx];
            $desc = (string) ($row['description'] ?? '');
            $date = (string) ($row['date'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);
            $keyStmt = $this->matchKeys->matchKey($date, $amount, $desc);

            $match = $matches[$idx];
            $meta = $matchMeta[$idx];

            $matchedViaThinBucket = $meta['matched_via_thin_bucket'];
            $matchedViaThinAmountOnly = $meta['matched_via_thin_amount'];
            $matchedViaBundle = $meta['matched_via_bundle'];

            $ledgerTransactionDate = null;
            if ($match !== null) {
                $ledgerTransactionDate = $match->transaction_date instanceof Carbon
                    ? $match->transaction_date->format('Y-m-d')
                    : Carbon::parse((string) $match->transaction_date)->format('Y-m-d');
            }

            $dateDrift = $match !== null && $date !== '' && $ledgerTransactionDate !== null && $ledgerTransactionDate !== $date;

            $status = 'missing_in_db';
            $existingId = null;
            $displayDescription = $desc;
            $needsDetailReasons = [];

            if ($match !== null) {
                $existingId = $match->id;
                $thinDb = $this->matchKeys->isDescriptionThin((string) ($match->description ?? ''));
                $needsDetailReasons = $this->needsDetailReasons(
                    $thinDb,
                    $matchedViaThinBucket,
                    $matchedViaThinAmountOnly,
                    $dateDrift,
                    $matchedViaBundle,
                );
                $needsDetail = $needsDetailReasons !== [];

                if ($needsDetail && trim($desc) !== '') {
                    $status = 'matched_needs_enrichment';
                    $displayDescription = $desc;
                    $counts['matched_needs_enrichment']++;
                    $this->logNeedsDetailRow(
                        $idx,
                        $row,
                        $accountId,
                        $date,
                        $ledgerTransactionDate,
                        $amount,
                        $existingId,
                        $needsDetailReasons,
                        (string) ($meta['miss_reason'] ?? 'unknown'),
                        $desc,
                        (string) ($match->description ?? ''),
                    );
                } elseif (! $needsDetail) {
                    $status = 'matched_complete';
                    $counts['matched_complete']++;
                } else {
                    $status = 'matched_complete';
                    $counts['matched_complete']++;
                }
            } else {
                $counts['missing_in_db']++;
            }

            $hints = $this->ledgerCategoryHints($match);

            $merged = array_merge($row, [
                'reconcile_status' => $status,
                'existing_transaction_id' => $existingId,
                'match_key' => $keyStmt,
                'display_description' => $displayDescription,
                'db_description_snapshot' => $match ? (string) ($match->description ?? '') : null,
                'db_reference_snapshot' => $match ? (string) ($match->reference_number ?? '') : null,
                'ledger_category_id' => $hints['ledger_category_id'],
                'ledger_subcategory_id' => $hints['ledger_subcategory_id'],
                'date_drift' => $dateDrift,
                'ledger_transaction_date' => $ledgerTransactionDate,
                'matched_via_bundle' => $matchedViaBundle,
                'bundle_id' => $meta['bundle_id'],
                'needs_detail_reasons' => $needsDetailReasons,
            ]);

            if ($debugReconcile) {
                $merged['match_attempt_reason'] = $match !== null
                    ? (string) ($meta['miss_reason'] ?? 'unknown')
                    : (string) ($meta['miss_reason'] ?? 'miss_unknown');
            }

            $out[] = $merged;
        }

        return [
            'period' => $period,
            'summary' => [
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'missing_in_db' => $counts['missing_in_db'],
                'matched_complete' => $counts['matched_complete'],
                'matched_needs_enrichment' => $counts['matched_needs_enrichment'],
                'balance' => $balanceSummary,
            ],
            'transactions' => $out,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function needsDetailReasons(
        bool $thinDb,
        bool $matchedViaThinBucket,
        bool $matchedViaThinAmountOnly,
        bool $dateDrift,
        bool $matchedViaBundle,
    ): array {
        $reasons = [];

        if ($thinDb) {
            $reasons[] = 'existing_description_thin';
        }
        if ($matchedViaThinBucket) {
            $reasons[] = 'matched_by_date_amount_bucket';
        }
        if ($matchedViaThinAmountOnly) {
            $reasons[] = 'matched_by_amount_proximity';
        }
        if ($dateDrift) {
            $reasons[] = 'statement_date_differs_from_ledger';
        }
        if ($matchedViaBundle) {
            $reasons[] = 'matched_via_split_bundle';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $needsDetailReasons
     */
    private function logNeedsDetailRow(
        int $idx,
        array $row,
        int $accountId,
        string $date,
        ?string $ledgerTransactionDate,
        float $amount,
        int $existingId,
        array $needsDetailReasons,
        string $matchAttemptReason,
        string $statementDescription,
        string $ledgerDescription,
    ): void {
        try {
            Log::info('Statement reconciliation row needs detail', [
                'row_index' => $idx,
                'statement_sequence' => $row['statement_sequence'] ?? null,
                'account_id' => $accountId,
                'statement_date' => $date,
                'ledger_transaction_date' => $ledgerTransactionDate,
                'amount' => $amount,
                'type' => $row['type'] ?? null,
                'existing_transaction_id' => $existingId,
                'reasons' => $needsDetailReasons,
                'match_attempt_reason' => $matchAttemptReason,
                'statement_description_preview' => Str::limit($statementDescription, 160),
                'ledger_description_preview' => Str::limit($ledgerDescription, 160),
            ]);
        } catch (\Throwable) {
            // Reconciliation should never fail just because the log sink is unavailable.
        }
    }

    /**
     * Map parsed row index → ledger txn + bundle id when saved bundles fully match this upload.
     *
     * @return array<int, array{txn: Transaction, bundle_id: int}>
     */
    private function resolveBundleAssignments(array $parsedTransactions, int $accountId): array
    {
        $bundles = StatementLedgerBundle::query()
            ->where('account_id', $accountId)
            ->with(['rows'])
            ->orderBy('id')
            ->get();

        /** @var array<int, array{txn: Transaction, bundle_id: int}> */
        $assignments = [];

        foreach ($bundles as $bundle) {
            $ledger = Transaction::query()->with(['category.parent'])->find($bundle->ledger_transaction_id);
            if ($ledger === null || (int) $ledger->account_id !== $accountId) {
                continue;
            }

            $lines = $bundle->rows;
            if ($lines->isEmpty()) {
                continue;
            }

            $sumLines = round((float) $lines->sum(fn ($line) => (float) $line->amount), 2);
            if (abs($sumLines - (float) $ledger->amount) > self::CHAIN_EPSILON) {
                continue;
            }

            /** @var array<int, true> */
            $parsedConsumed = [];
            /** @var array<int, true> */
            $matchedIdx = [];

            foreach ($lines as $line) {
                $lineDate = $line->statement_date instanceof Carbon
                    ? $line->statement_date->format('Y-m-d')
                    : Carbon::parse((string) $line->statement_date)->format('Y-m-d');

                $foundIdx = null;
                foreach ($parsedTransactions as $idx => $row) {
                    if (isset($parsedConsumed[$idx])) {
                        continue;
                    }
                    if (isset($assignments[$idx])) {
                        continue;
                    }

                    $seq = $row['statement_sequence'] ?? null;
                    if ($line->statement_sequence !== null) {
                        if ($seq === null || (int) $seq !== (int) $line->statement_sequence) {
                            continue;
                        }
                    }

                    $rowDate = (string) ($row['date'] ?? '');
                    if ($rowDate !== $lineDate) {
                        continue;
                    }

                    if (abs((float) ($row['amount'] ?? 0) - (float) $line->amount) > self::CHAIN_EPSILON) {
                        continue;
                    }

                    $foundIdx = $idx;
                    break;
                }

                if ($foundIdx === null) {
                    $matchedIdx = [];
                    break;
                }

                $matchedIdx[$foundIdx] = true;
                $parsedConsumed[$foundIdx] = true;
            }

            if ($matchedIdx === [] || count($matchedIdx) !== $lines->count()) {
                continue;
            }

            foreach (array_keys($matchedIdx) as $idx) {
                $assignments[(int) $idx] = ['txn' => $ledger, 'bundle_id' => (int) $bundle->id];
            }
        }

        return $assignments;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array<int, int>
     */
    private function sortedStatementIndices(array $parsedTransactions): array
    {
        $n = count($parsedTransactions);
        $indices = range(0, max(0, $n - 1));
        usort($indices, function (int $a, int $b) use ($parsedTransactions): int {
            $sa = $parsedTransactions[$a]['statement_sequence'] ?? null;
            $sb = $parsedTransactions[$b]['statement_sequence'] ?? null;
            if ($sa !== null && $sb !== null) {
                return ((int) $sa) <=> ((int) $sb);
            }
            if ($sa !== null) {
                return -1;
            }
            if ($sb !== null) {
                return 1;
            }

            return $a <=> $b;
        });

        return $indices;
    }

    /**
     * @param  array<int, Transaction>  $candidates
     * @param  array<int, true>  $usedLedgerIds
     */
    private function pickNearestUnusedThin(
        array $candidates,
        string $statementDateYmd,
        string $statementType,
        array &$usedLedgerIds,
        ?int $proximityMaxDays,
    ): ?Transaction {
        $eligible = [];
        foreach ($candidates as $txn) {
            if (isset($usedLedgerIds[$txn->id])) {
                continue;
            }
            if (! $this->statementTypeMatchesLedger($statementType, (string) $txn->transaction_type)) {
                continue;
            }

            $ledgerDate = $txn->transaction_date instanceof Carbon
                ? $txn->transaction_date->format('Y-m-d')
                : Carbon::parse((string) $txn->transaction_date)->format('Y-m-d');

            $dayDist = 0;
            if ($statementDateYmd !== '') {
                try {
                    $dayDist = (int) abs(Carbon::parse($statementDateYmd)->diffInDays(Carbon::parse($ledgerDate)));
                } catch (\Throwable) {
                    continue;
                }
            }

            if ($proximityMaxDays !== null && $dayDist > $proximityMaxDays) {
                continue;
            }

            $eligible[] = ['txn' => $txn, 'dist' => $dayDist];
        }

        if ($eligible === []) {
            return null;
        }

        usort($eligible, function (array $a, array $b): int {
            if ($a['dist'] !== $b['dist']) {
                return $a['dist'] <=> $b['dist'];
            }

            return $a['txn']->id <=> $b['txn']->id;
        });

        return $eligible[0]['txn'];
    }

    private function statementTypeMatchesLedger(string $statementType, string $ledgerType): bool
    {
        return match ($statementType) {
            'income' => $ledgerType === 'income',
            'expense' => $ledgerType === 'expense',
            default => false,
        };
    }

    /**
     * @param  Collection<int, Transaction>|\Illuminate\Database\Eloquent\Collection<int, Transaction>  $dbExpanded
     * @param  array{start: string|null, end: string|null}  $period
     * @return Collection<int, Transaction>
     */
    private function narrowDbTransactions(Collection $dbExpanded, array $period): Collection
    {
        if ($period['start'] === null || $period['end'] === null) {
            return $dbExpanded;
        }

        return $dbExpanded->filter(function (Transaction $txn) use ($period): bool {
            $d = $txn->transaction_date instanceof Carbon
                ? $txn->transaction_date->format('Y-m-d')
                : Carbon::parse((string) $txn->transaction_date)->format('Y-m-d');

            return $d >= $period['start'] && $d <= $period['end'];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array{start: string|null, end: string|null}
     */
    private function derivePeriod(array $parsedTransactions): array
    {
        $dates = [];
        foreach ($parsedTransactions as $row) {
            $d = $row['date'] ?? null;
            if (is_string($d) && $d !== '') {
                try {
                    $dates[] = Carbon::parse($d)->format('Y-m-d');
                } catch (\Throwable) {
                    //
                }
            }
        }
        if ($dates === []) {
            return ['start' => null, 'end' => null];
        }

        sort($dates);

        return ['start' => $dates[0], 'end' => $dates[count($dates) - 1]];
    }

    private function thinDbBucketKey(string $dateYmd, float $amount): string
    {
        return $dateYmd.'_'.$this->matchKeys->formatAmountForKey($amount).'_thin_bucket';
    }

    /**
     * Savings-account convention: balance_after = balance_before - debit + credit.
     *
     * @param  array<int, array<string, mixed>>  $sortedTransactions
     * @return array<string, mixed>
     */
    private function computeBalanceSummary(
        array $sortedTransactions,
        ?int $accountId,
        ?string $expandedStart,
        ?string $expandedEnd,
    ): array {
        $rowsWithBalance = [];
        foreach ($sortedTransactions as $i => $row) {
            if (isset($row['balance_after']) && $row['balance_after'] !== null && is_numeric($row['balance_after'])) {
                $rowsWithBalance[] = ['index' => $i, 'row' => $row];
            }
        }

        if ($rowsWithBalance === []) {
            return [
                'chain_ok' => null,
                'chain_skipped_reason' => 'no_balance_column',
                'broken_at_statement_sequence' => null,
                'broken_at_index' => null,
                'derived_opening_before_first' => null,
                'statement_closing_balance' => null,
                'net_delta_statement_minus_db' => null,
                'opening_matches_prior_row' => null,
                'matches_initial_balance_txn' => null,
                'broken_at_detail' => null,
            ];
        }

        $first = $rowsWithBalance[0]['row'];
        $firstIdx = $rowsWithBalance[0]['index'];
        $debit0 = (float) ($first['debit_amount'] ?? 0);
        $credit0 = (float) ($first['credit_amount'] ?? 0);
        $bal0 = (float) $first['balance_after'];
        $derivedOpening = round($bal0 + $debit0 - $credit0, 2);

        $openingMatchesPriorRow = null;
        if ($firstIdx > 0) {
            $prior = $sortedTransactions[$firstIdx - 1];
            if (isset($prior['balance_after']) && $prior['balance_after'] !== null && is_numeric($prior['balance_after'])) {
                $openingMatchesPriorRow = abs((float) $prior['balance_after'] - $derivedOpening) <= self::CHAIN_EPSILON;
            }
        }

        $chainOk = true;
        $brokenAtIndex = null;
        $brokenSeq = null;
        $brokenDetail = null;

        for ($k = 1; $k < count($rowsWithBalance); $k++) {
            $prev = $rowsWithBalance[$k - 1];
            $prevBal = (float) $prev['row']['balance_after'];
            $curWithIndex = $rowsWithBalance[$k];
            $cur = $curWithIndex['row'];
            $debit = (float) ($cur['debit_amount'] ?? 0);
            $credit = (float) ($cur['credit_amount'] ?? 0);
            $expected = round($prevBal - $debit + $credit, 2);
            $actual = round((float) $cur['balance_after'], 2);
            if (abs($expected - $actual) > self::CHAIN_EPSILON) {
                $chainOk = false;
                $brokenAtIndex = $curWithIndex['index'];
                $brokenSeq = $cur['statement_sequence'] ?? null;
                $brokenDetail = [
                    'previous_index' => $prev['index'],
                    'previous_statement_sequence' => $prev['row']['statement_sequence'] ?? null,
                    'previous_balance_after' => round($prevBal, 2),
                    'current_index' => $curWithIndex['index'],
                    'current_statement_sequence' => $cur['statement_sequence'] ?? null,
                    'current_date' => $cur['date'] ?? null,
                    'current_description' => $cur['description'] ?? null,
                    'current_debit_amount' => round($debit, 2),
                    'current_credit_amount' => round($credit, 2),
                    'expected_balance_after' => $expected,
                    'actual_balance_after' => $actual,
                    'difference' => round($actual - $expected, 2),
                ];
                break;
            }
        }

        $lastRow = $rowsWithBalance[count($rowsWithBalance) - 1]['row'];

        $matchesInitialBalanceTxn = null;
        if ($accountId !== null && $expandedStart !== null && $expandedEnd !== null) {
            $patterns = config('statement.initial_balance_description_patterns', []);
            $patterns = is_array($patterns) ? array_values(array_filter($patterns, fn ($p) => is_string($p) && $p !== '')) : [];

            $matchesInitialBalanceTxn = false;
            $candidates = Transaction::query()
                ->where('account_id', $accountId)
                ->whereDate('transaction_date', '>=', $expandedStart)
                ->whereDate('transaction_date', '<=', $expandedEnd)
                ->whereIn('transaction_type', ['income', 'expense'])
                ->get(['amount', 'description']);

            foreach ($candidates as $cand) {
                $descLower = mb_strtolower((string) ($cand->description ?? ''));
                foreach ($patterns as $p) {
                    if ($descLower !== '' && str_contains($descLower, mb_strtolower($p))) {
                        $matchesInitialBalanceTxn = true;
                        break 2;
                    }
                }
                if (abs((float) $cand->amount - $derivedOpening) <= self::CHAIN_EPSILON) {
                    $matchesInitialBalanceTxn = true;
                    break;
                }
            }
        }

        return [
            'chain_ok' => $chainOk,
            'chain_skipped_reason' => null,
            'broken_at_statement_sequence' => $brokenSeq,
            'broken_at_index' => $brokenAtIndex,
            'derived_opening_before_first' => $derivedOpening,
            'statement_closing_balance' => isset($lastRow['balance_after']) ? (float) $lastRow['balance_after'] : null,
            'opening_matches_prior_row' => $openingMatchesPriorRow,
            'matches_initial_balance_txn' => $matchesInitialBalanceTxn,
            'broken_at_detail' => $brokenDetail,
        ];
    }

    /**
     * Category IDs from an existing ledger row for Review defaults (expense = parent + leaf).
     *
     * @return array{ledger_category_id: ?int, ledger_subcategory_id: ?int}
     */
    private function ledgerCategoryHints(?Transaction $match): array
    {
        if ($match === null || ! in_array($match->transaction_type, ['income', 'expense'], true)) {
            return ['ledger_category_id' => null, 'ledger_subcategory_id' => null];
        }

        $leaf = $match->category;
        if ($leaf === null) {
            return ['ledger_category_id' => null, 'ledger_subcategory_id' => null];
        }

        if ($match->transaction_type === 'income') {
            return ['ledger_category_id' => null, 'ledger_subcategory_id' => $leaf->id];
        }

        if ($leaf->parent_id !== null) {
            return ['ledger_category_id' => (int) $leaf->parent_id, 'ledger_subcategory_id' => $leaf->id];
        }

        return ['ledger_category_id' => $leaf->id, 'ledger_subcategory_id' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     */
    private function statementNetDelta(array $parsedTransactions): float
    {
        $sum = 0.0;
        foreach ($parsedTransactions as $row) {
            $sum += (float) ($row['credit_amount'] ?? 0) - (float) ($row['debit_amount'] ?? 0);
        }

        return round($sum, 2);
    }

    /**
     * Net cash impact of ledger rows in the reconcile window (income minus expense magnitude).
     *
     * @param  Collection<int, Transaction>|\Illuminate\Database\Eloquent\Collection<int, Transaction>  $dbTxns
     */
    private function dbNetDelta(Collection $dbTxns): float
    {
        $sum = 0.0;
        foreach ($dbTxns as $txn) {
            if ($txn->transaction_type === 'income') {
                $sum += (float) $txn->amount;
            } else {
                $sum -= (float) $txn->amount;
            }
        }

        return round($sum, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array<int, array<string, mixed>>
     */
    private function annotateUnscoped(array $parsedTransactions): array
    {
        $debugReconcile = (bool) config('statement.reconcile_debug', false);
        $out = [];
        foreach ($parsedTransactions as $row) {
            $desc = (string) ($row['description'] ?? '');
            $date = (string) ($row['date'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);
            $merged = array_merge($row, [
                'reconcile_status' => 'unscoped',
                'existing_transaction_id' => null,
                'match_key' => $this->matchKeys->matchKey($date, $amount, $desc),
                'display_description' => $desc,
                'db_description_snapshot' => null,
                'db_reference_snapshot' => null,
                'ledger_category_id' => null,
                'ledger_subcategory_id' => null,
                'date_drift' => false,
                'ledger_transaction_date' => null,
                'matched_via_bundle' => false,
                'bundle_id' => null,
                'needs_detail_reasons' => [],
            ]);
            if ($debugReconcile) {
                $merged['match_attempt_reason'] = 'unscoped_account';
            }
            $out[] = $merged;
        }

        return $out;
    }
}
