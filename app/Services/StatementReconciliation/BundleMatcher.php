<?php

namespace App\Services\StatementReconciliation;

use App\Models\StatementLedgerBundle;
use App\Models\Transaction;
use Carbon\Carbon;

class BundleMatcher
{
    private const CHAIN_EPSILON = 0.02;

    /**
     * Map parsed row index → ledger txn + bundle id when saved bundles fully match this upload.
     *
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array<int, array{txn: Transaction, bundle_id: int}>
     */
    public function resolveBundleAssignments(array $parsedTransactions, int $accountId): array
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
}
