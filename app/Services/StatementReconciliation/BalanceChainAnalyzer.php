<?php

namespace App\Services\StatementReconciliation;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class BalanceChainAnalyzer
{
    private const CHAIN_EPSILON = 0.02;

    /**
     * Savings-account convention: balance_after = balance_before - debit + credit.
     *
     * @param  array<int, array<string, mixed>>  $sortedTransactions
     * @return array<string, mixed>
     */
    public function computeBalanceSummary(
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
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     */
    public function statementNetDelta(array $parsedTransactions): float
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
    public function dbNetDelta(Collection $dbTxns): float
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
}
