<?php

namespace App\Reporting;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * SUM + COUNT per category, computed in one grouped query, with child totals
 * rolled up into their parents in PHP (the category tree is exactly two levels).
 *
 * Totals keep their long-standing meaning: every transaction type (income,
 * expense, and transfer legs) counts, exactly as the category pages showed
 * before. Budget spending has its own expense-only rules and is not part of
 * this class.
 */
final class CategoryTotals
{
    /**
     * Per-category totals for the given ids, keyed by category id. Categories
     * without any transaction rows are absent from the result.
     *
     * @param  array<int, int|null>  $categoryIds
     * @return array<int, array{total: float, count: int}>
     */
    public function totalsForCategoryIds(array $categoryIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $categoryIds,
            fn ($id) => $id !== null && $id !== ''
        )));

        if ($ids === []) {
            return [];
        }

        $rows = Transaction::query()
            ->toBase()
            ->selectRaw('category_id, SUM(amount) AS total, COUNT(*) AS row_count')
            ->whereIn('category_id', $ids)
            ->groupBy('category_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->category_id] = [
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->row_count,
            ];
        }

        return $totals;
    }

    /**
     * Add each parent's child totals to its own. Child rows keep their own
     * totals, so a parent reads its rolled-up value while its children read
     * theirs untouched — the same shape the category pages exposed before.
     *
     * @param  array<int, array{total: float, count: int}>  $ownTotals
     * @param  Collection<int, Category>  $categories  categories with their children loaded
     * @return array<int, array{total: float, count: int}>
     */
    public function rollUpChildren(array $ownTotals, Collection $categories): array
    {
        $totals = $ownTotals;

        foreach ($categories as $category) {
            if ($category->parent_id !== null) {
                continue;
            }

            foreach ($category->children as $child) {
                $childTotal = $totals[$child->id] ?? ['total' => 0.0, 'count' => 0];
                $parentTotal = $totals[$category->id] ?? ['total' => 0.0, 'count' => 0];

                $totals[$category->id] = [
                    'total' => round($parentTotal['total'] + $childTotal['total'], 2),
                    'count' => $parentTotal['count'] + $childTotal['count'],
                ];
            }
        }

        return $totals;
    }
}
