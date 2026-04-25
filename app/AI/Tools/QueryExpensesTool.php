<?php

namespace App\AI\Tools;

use App\Models\Transaction;
use Carbon\Carbon;
use Prism\Prism\Tool;

class QueryExpensesTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('query_expenses')
            ->for('Query expenses/income for a time period: totals, counts, top categories, and optionally the most recent matching rows. Use list_limit (1–20) when the user wants to see recent transactions, last transaction, or a list — omit or use 0 for summary-only.')
            ->withEnumParameter('period', 'One of: today, this_week, this_month, last_month, all', ['today', 'this_week', 'this_month', 'last_month', 'all'])
            ->withEnumParameter('type', 'One of: expense, income, both', ['expense', 'income', 'both'])
            ->withNumberParameter('list_limit', 'Optional. If 1–20, return up to that many recent rows (date, description, amount, type, category, account) matching period+type, newest first. Use 0 or omit for aggregates only.', false)
            ->using($this->execute(...));
    }

    public function execute(string $period, string $type, ?float $list_limit = null): string
    {
        try {
            $base = Transaction::query();

            match ($period) {
                'today' => $base->whereDate('transaction_date', Carbon::today()),
                'this_week' => $base->whereBetween('transaction_date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]),
                'this_month' => $base->whereYear('transaction_date', Carbon::now()->year)
                    ->whereMonth('transaction_date', Carbon::now()->month),
                'last_month' => $base->whereYear('transaction_date', Carbon::now()->subMonth()->year)
                    ->whereMonth('transaction_date', Carbon::now()->subMonth()->month),
                'all' => null,
                default => null,
            };

            if ($type !== 'both') {
                $base->where('transaction_type', $type);
            }

            $total = (clone $base)->sum('amount');
            $count = (clone $base)->count();

            $topCategories = (clone $base)
                ->with('category')
                ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('category_id')
                ->orderByDesc('total')
                ->limit(3)
                ->get();

            $categoryBreakdown = $topCategories->map(fn ($t) =>
                ($t->category?->name ?? 'Uncategorized').': ₹'.number_format($t->total, 2).' ('.$t->count.' txn)'
            )->join(', ');

            $incomeTotal = 0;
            $expenseTotal = 0;
            if ($type === 'both') {
                $incomeTotal = (clone $base)->where('transaction_type', 'income')->sum('amount');
                $expenseTotal = (clone $base)->where('transaction_type', 'expense')->sum('amount');
            }

            $transactions = [];
            $listCap = $list_limit !== null ? (int) round($list_limit) : 0;
            if ($listCap > 0) {
                $n = max(1, min(20, $listCap));
                $rows = (clone $base)
                    ->with(['category', 'account'])
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->limit($n)
                    ->get();

                $transactions = $rows->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'date' => $t->transaction_date->format('Y-m-d'),
                    'description' => $t->description,
                    'amount' => (float) $t->amount,
                    'type' => $t->transaction_type,
                    'category' => $t->category?->name,
                    'account' => $t->account?->name,
                ])->values()->all();
            }

            return json_encode([
                'success' => true,
                'period' => $period,
                'type' => $type,
                'total' => number_format($total, 2),
                'count' => $count,
                'income_total' => $type === 'both' ? number_format($incomeTotal, 2) : null,
                'expense_total' => $type === 'both' ? number_format($expenseTotal, 2) : null,
                'net' => $type === 'both' ? number_format($incomeTotal - $expenseTotal, 2) : null,
                'top_categories' => $categoryBreakdown ?: 'No transactions found',
                'transactions' => $transactions,
                'message' => $count > 0
                    ? "📊 {$count} transactions totaling ₹{$total}"
                    : "No transactions found for {$period}",
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Failed to query expenses: '.$e->getMessage(),
            ]);
        }
    }
}
