<?php

namespace App\AI\Tools;

use App\Models\Transaction;
use App\Reporting\FinancialSummary;
use App\Reporting\TransactionFilters;
use App\Reporting\TransactionQuery;
use Prism\Prism\Tool;

class QueryExpensesTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('query_expenses')
            ->for('Query expenses/income for a time period: totals, counts, top categories, and optionally the most recent matching rows. Use list_limit (1–20) when the user wants to see recent transactions, last transaction, or a list — omit or use 0 for summary-only.')
            ->withEnumParameter('period', 'One of: today, this_week, this_month, last_month, all', TransactionFilters::PERIODS)
            ->withEnumParameter('type', 'One of: expense, income, both', ['expense', 'income', 'both'])
            ->withNumberParameter('list_limit', 'Optional. If 1–20, return up to that many recent rows (date, description, amount, type, category, account) matching period+type, newest first. Use 0 or omit for aggregates only.', false)
            ->using($this->execute(...));
    }

    public function execute(string $period, string $type, ?float $list_limit = null): string
    {
        try {
            // "both" means income + expense; transfers are never counted as either.
            $filters = TransactionFilters::all()->forPeriod($period)->ofType($type);
            $summary = app(FinancialSummary::class);
            $totals = $summary->totals($filters);

            $total = match ($type) {
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                default => $totals['net'],
            };

            $categoryBreakdown = $summary->topCategories($filters, 3)
                ->map(fn (array $row) => $row['category'].': ₹'.number_format($row['total'], 2).' ('.$row['count'].' txn)')
                ->join(', ');

            $transactions = [];
            $listCap = $list_limit !== null ? (int) round($list_limit) : 0;
            if ($listCap > 0) {
                $transactions = TransactionQuery::for($filters)
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->limit(max(1, min(20, $listCap)))
                    ->get()
                    ->map(fn (Transaction $t) => [
                        'id' => $t->id,
                        'date' => $t->transaction_date->format('Y-m-d'),
                        'description' => $t->description,
                        'amount' => (float) $t->amount,
                        'type' => $t->transaction_type,
                        'category' => $t->category?->name,
                        'account' => $t->account?->name,
                    ])->values()->all();
            }

            $count = $totals['count'];

            return json_encode([
                'success' => true,
                'period' => $period,
                'type' => $type,
                'total' => number_format($total, 2),
                'count' => $count,
                'income_total' => $type === 'both' ? number_format($totals['income'], 2) : null,
                'expense_total' => $type === 'both' ? number_format($totals['expense'], 2) : null,
                'net' => $type === 'both' ? number_format($totals['net'], 2) : null,
                'top_categories' => $categoryBreakdown ?: 'No transactions found',
                'transactions' => $transactions,
                'message' => match (true) {
                    $count === 0 => "No transactions found for {$period}",
                    $type === 'both' => "📊 {$count} transactions: income ₹".number_format($totals['income'], 2)
                        .', expenses ₹'.number_format($totals['expense'], 2).', net ₹'.number_format($totals['net'], 2),
                    default => "📊 {$count} transactions totaling ₹".number_format($total, 2),
                },
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Failed to query expenses: '.$e->getMessage(),
            ]);
        }
    }
}
