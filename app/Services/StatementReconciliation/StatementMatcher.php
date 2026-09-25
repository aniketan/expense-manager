<?php

namespace App\Services\StatementReconciliation;

use App\Models\Transaction;
use App\Services\StatementMatchKeyService;
use Carbon\Carbon;

class StatementMatcher
{
    public function __construct(
        private StatementMatchKeyService $matchKeys,
        private ?StatementCandidateIndex $candidateIndex = null,
    ) {
        $this->candidateIndex = $candidateIndex ?? new StatementCandidateIndex($matchKeys);
    }

    /**
     * Match every parsed row against the indexed ledger candidates.
     *
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @param  array{
     *     firstByKey: array<string, Transaction>,
     *     thinBucketsMulti: array<string, array<int, Transaction>>,
     *     secondaryBuckets: array<string, array<int, Transaction>>,
     *     thinAmountBuckets: array<string, array<int, Transaction>>
     * }  $index
     * @param  array<int, array{txn: Transaction, bundle_id: int}>  $bundleAssignments
     * @return array{0: array<int, Transaction|null>, 1: array<int, array<string, mixed>>}
     */
    public function match(
        array $parsedTransactions,
        array $index,
        array $bundleAssignments,
        ?int $thinProximityMaxDays,
    ): array {
        $firstByKey = $index['firstByKey'];
        $thinBucketsMulti = $index['thinBucketsMulti'];
        $secondaryBuckets = $index['secondaryBuckets'];
        $thinAmountBuckets = $index['thinAmountBuckets'];

        /** @var array<int, true> $usedLedgerIds */
        $usedLedgerIds = [];

        foreach ($bundleAssignments as $assignment) {
            $usedLedgerIds[$assignment['txn']->id] = true;
        }

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
                $bk = $this->candidateIndex->thinDbBucketKey($date, $amount);
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

        return [$matches, $matchMeta];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array<int, int>
     */
    public function sortedStatementIndices(array $parsedTransactions): array
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
    public function pickNearestUnusedThin(
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

    public function statementTypeMatchesLedger(string $statementType, string $ledgerType): bool
    {
        return match ($statementType) {
            'income' => $ledgerType === 'income',
            'expense' => $ledgerType === 'expense',
            default => false,
        };
    }
}
