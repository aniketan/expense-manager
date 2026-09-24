<?php

namespace App\Reporting;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Income, expense, and net totals computed one way for every surface.
 * Transfers only move money between accounts, so they are never income or expense.
 */
final class FinancialSummary
{
    /**
     * `count` is every row matching the filters, including transfers when the filters include them.
     *
     * @return array{income: float, expense: float, net: float, count: int}
     */
    public function totals(TransactionFilters $filters): array
    {
        $row = TransactionQuery::apply(Transaction::query(), $filters)
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE 0 END), 0) AS income, '.
                'COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE 0 END), 0) AS expense, '.
                'COUNT(*) AS row_count',
                [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE]
            )
            ->first();

        $income = round((float) $row->income, 2);
        $expense = round((float) $row->expense, 2);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 2),
            'count' => (int) $row->row_count,
        ];
    }

    /**
     * @return Collection<int, array{category_id: ?int, category: string, full_name: string, total: float, count: int}>
     */
    public function topCategories(TransactionFilters $filters, int $limit = 5): Collection
    {
        return TransactionQuery::apply(Transaction::query()->with('category.parent'), $filters)
            ->selectRaw('category_id, SUM(amount) AS total, COUNT(*) AS row_count')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $row) => [
                'category_id' => $row->category_id,
                'category' => $row->category?->name ?? 'Uncategorized',
                'full_name' => $row->category
                    ? ($row->category->parent ? $row->category->parent->name.' > ' : '').$row->category->name
                    : 'Uncategorized',
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->row_count,
            ])
            ->values();
    }

    /**
     * @return list<array{month: string, income: float, expense: float, balance: float}>
     */
    public function monthlyTrend(int $months = 12, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $trend = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $now->startOfMonth()->subMonthsNoOverflow($i);
            $totals = $this->totals(new TransactionFilters(
                dateFrom: $month->toDateString(),
                dateTo: $month->endOfMonth()->toDateString(),
            ));

            $trend[] = [
                'month' => $month->format('M Y'),
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                'balance' => $totals['net'],
            ];
        }

        return $trend;
    }
}
