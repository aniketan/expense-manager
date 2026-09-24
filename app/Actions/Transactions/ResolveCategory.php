<?php

namespace App\Actions\Transactions;

use App\Models\Category;
use App\Models\Transaction;

/**
 * The single source of truth for which categories a regular transaction may use.
 */
class ResolveCategory
{
    /**
     * Match a free-text hint against categories valid for the type, then fall back
     * to "Other", then to any valid leaf.
     */
    public function forType(string $transactionType, ?string $hint): ?Category
    {
        $hint = trim((string) $hint);

        if ($hint !== '') {
            $match = Category::assignableFor($transactionType)
                ->where('name', 'like', "%{$hint}%")
                ->orderBy('id')
                ->first();

            if ($match) {
                return $match;
            }
        }

        return Category::assignableFor($transactionType)->where('name', 'Other')->orderBy('id')->first()
            ?? Category::assignableFor($transactionType)->orderBy('id')->first();
    }

    /**
     * Income must sit under the Income tree; expenses must stay out of the Income and
     * Account Transfer trees; transfer legs are never assignable. Parent categories are
     * allowed, since the web form has always accepted them.
     */
    public function ensureCompatible(mixed $categoryId, string $transactionType): Category
    {
        $category = $categoryId ? Category::with('parent')->find($categoryId) : null;

        if (! $category) {
            throw TransactionRuleViolation::on('category_id', 'Choose a category.');
        }

        if ($category->isTransferCategory()) {
            throw TransactionRuleViolation::on('category_id', 'Transfer categories are reserved for account transfers. Choose the Transfer type instead.');
        }

        // The tree is two levels deep (#76), so the root is the parent or the category itself.
        $root = $category->parent ?? $category;

        if ($transactionType === Transaction::TYPE_INCOME
            && ! $root->isIncomeRoot()
            && Category::query()->incomeRoot()->exists()) {
            throw TransactionRuleViolation::on('category_id', 'Income transactions need a category under Income.');
        }

        if ($transactionType === Transaction::TYPE_EXPENSE && ($root->isIncomeRoot() || $root->isTransferRoot())) {
            throw TransactionRuleViolation::on('category_id', 'Expense transactions cannot use an income or account-transfer category.');
        }

        return $category;
    }
}
