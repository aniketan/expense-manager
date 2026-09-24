<?php

namespace App\Reporting;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns TransactionFilters into a query. Every listing and total goes through here.
 */
final class TransactionQuery
{
    public static function for(TransactionFilters $filters): Builder
    {
        return self::apply(Transaction::query()->with(['category.parent', 'account']), $filters);
    }

    public static function apply(Builder $query, TransactionFilters $filters): Builder
    {
        if ($filters->types !== null) {
            $query->whereIn('transactions.transaction_type', $filters->types);
        }

        if ($filters->categoryIds !== null) {
            $query->whereIn('transactions.category_id', $filters->categoryIds);
        }

        if ($filters->accountIds !== null) {
            $query->whereIn('transactions.account_id', $filters->accountIds);
        }

        // Dates are stored as "Y-m-d 00:00:00"; comparing the date part keeps both bounds inclusive.
        if ($filters->dateFrom !== null) {
            $query->whereDate('transactions.transaction_date', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('transactions.transaction_date', '<=', $filters->dateTo);
        }

        if ($filters->search !== null) {
            $search = $filters->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('transactions.description', 'like', "%{$search}%")
                    ->orWhere('transactions.notes', 'like', "%{$search}%")
                    ->orWhere('transactions.payee_payer', 'like', "%{$search}%")
                    ->orWhere('transactions.reference_number', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($filters->paymentMethod !== null) {
            $query->where('transactions.payment_method', $filters->paymentMethod);
        }

        if ($filters->status !== null) {
            $query->where('transactions.status', $filters->status);
        }

        if ($filters->tags !== null) {
            $query->where('transactions.tags', 'like', "%{$filters->tags}%");
        }

        if ($filters->cashFlow === 'credit') {
            $query->where(fn (Builder $q) => $q
                ->where('transactions.transaction_type', Transaction::TYPE_INCOME)
                ->orWhereHas('category', fn (Builder $c) => $c->where('code', Transaction::CATEGORY_TRANSFER_INCOMING)));
        } elseif ($filters->cashFlow === 'debit') {
            $query->where(fn (Builder $q) => $q
                ->where('transactions.transaction_type', Transaction::TYPE_EXPENSE)
                ->orWhere(fn (Builder $t) => $t
                    ->where('transactions.transaction_type', Transaction::TYPE_TRANSFER)
                    ->whereDoesntHave('category', fn (Builder $c) => $c->where('code', Transaction::CATEGORY_TRANSFER_INCOMING))));
        }

        return $query;
    }

    /**
     * A category filter means the category and its subcategories (budget semantics).
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public static function expandCategoryIds(array $categoryIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $categoryIds)));
        if ($ids === []) {
            return [];
        }

        $childIds = Category::whereIn('parent_id', $ids)->pluck('id')->all();

        return array_values(array_unique(array_merge($ids, $childIds)));
    }
}
