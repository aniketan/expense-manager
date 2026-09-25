<?php

namespace App\Services\StatementReconciliation;

use App\Models\Transaction;
use App\Services\StatementMatchKeyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconciliationAnnotator
{
    public function __construct(
        private StatementMatchKeyService $matchKeys,
    ) {}

    /**
     * Build the per-row reconcile output (status, snapshots, hints) for matched rows.
     *
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @param  array<int, Transaction|null>  $matches
     * @param  array<int, array<string, mixed>>  $matchMeta
     * @return array{
     *     transactions: array<int, array<string, mixed>>,
     *     counts: array{missing_in_db: int, matched_complete: int, matched_needs_enrichment: int}
     * }
     */
    public function annotate(
        array $parsedTransactions,
        array $matches,
        array $matchMeta,
        int $accountId,
        bool $debugReconcile,
    ): array {
        $counts = ['missing_in_db' => 0, 'matched_complete' => 0, 'matched_needs_enrichment' => 0];

        $n = count($parsedTransactions);
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

        return ['transactions' => $out, 'counts' => $counts];
    }

    /**
     * @return array<int, string>
     */
    public function needsDetailReasons(
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
    public function logNeedsDetailRow(
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
     * Category IDs from an existing ledger row for Review defaults (expense = parent + leaf).
     *
     * @return array{ledger_category_id: ?int, ledger_subcategory_id: ?int}
     */
    public function ledgerCategoryHints(?Transaction $match): array
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
     * @return array<int, array<string, mixed>>
     */
    public function annotateUnscoped(array $parsedTransactions): array
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
