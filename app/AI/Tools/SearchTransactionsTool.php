<?php

namespace App\AI\Tools;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Reporting\FinancialSummary;
use App\Reporting\TransactionFilters;
use App\Reporting\TransactionQuery;
use Carbon\Carbon;
use Prism\Prism\Tool;

class SearchTransactionsTool extends Tool
{
    /** The values the app stores (see the transaction form). */
    public const PAYMENT_METHODS = ['UPI', 'Bank Transfer', 'Credit Card', 'Debit Card', 'Cash', 'Cheque', 'Other'];

    public function __construct()
    {
        $this
            ->as('search_transactions')
            ->for(
                'Search and filter transactions with full control over category, account, date range, description, payment method, and tags. '.
                'Use after list_categories to get real category IDs. '.
                'All parameters are optional except type. '.
                'Use list_limit (1–50) to control how many rows are returned. '.
                'Returns matching transactions plus aggregate totals and the matched category names.'
            )
            ->withEnumParameter(
                'type',
                'Filter by transaction type ("both" = income and expense)',
                ['expense', 'income', 'both']
            )
            ->withStringParameter(
                'category_ids',
                'Comma-separated category IDs obtained from list_categories (e.g. "5,12,18"). A parent category also matches its subcategories. Leave empty to search all categories.',
                false
            )
            ->withStringParameter(
                'from_date',
                'Start date in YYYY-MM-DD format (inclusive). Omit for no lower bound.',
                false
            )
            ->withStringParameter(
                'to_date',
                'End date in YYYY-MM-DD format (inclusive). Omit for no upper bound.',
                false
            )
            ->withStringParameter(
                'description_search',
                'Keyword to search inside transaction description, notes, payee, reference, or category name (case-insensitive partial match).',
                false
            )
            ->withStringParameter(
                'account_name',
                'Partial account name to filter by (e.g. "HDFC", "Cash"). Case-insensitive.',
                false
            )
            ->withEnumParameter(
                'payment_method',
                'Filter by payment method. Omit to include all.',
                self::PAYMENT_METHODS,
                false
            )
            ->withStringParameter(
                'tags',
                'Keyword to search inside the tags field (case-insensitive partial match).',
                false
            )
            ->withNumberParameter(
                'list_limit',
                'Number of transaction rows to return (1–50). Default 10.',
                false
            )
            ->withEnumParameter(
                'sort_by',
                'How to sort results. Default: newest.',
                ['newest', 'oldest', 'amount_desc', 'amount_asc'],
                false
            )
            ->using($this->execute(...));
    }

    public function execute(
        string $type,
        ?string $category_ids = null,
        ?string $from_date = null,
        ?string $to_date = null,
        ?string $description_search = null,
        ?string $account_name = null,
        ?string $payment_method = null,
        ?string $tags = null,
        ?float $list_limit = null,
        ?string $sort_by = null,
    ): string {
        try {
            $filters = TransactionFilters::all()->ofType($type);

            $matchedCategories = [];
            if (! empty($category_ids)) {
                $ids = array_values(array_filter(array_map('intval', explode(',', $category_ids))));
                if ($ids !== []) {
                    $filters = $filters->inCategories($ids);
                    $matchedCategories = Category::whereIn('id', $ids)->pluck('name')->values()->all();
                }
            }

            $filters = $filters->with([
                'dateFrom' => $this->parseDate($from_date),
                'dateTo' => $this->parseDate($to_date),
                'search' => $description_search ?: null,
                'paymentMethod' => $payment_method ?: null,
                'tags' => $tags ?: null,
            ]);

            if (! empty($account_name)) {
                $filters = $filters->with([
                    'accountIds' => Account::where('name', 'like', '%'.$account_name.'%')->pluck('id')->all(),
                ]);
            }

            $totals = app(FinancialSummary::class)->totals($filters);

            $query = TransactionQuery::for($filters);
            $sort = $sort_by ?? 'newest';
            match ($sort) {
                'oldest' => $query->orderBy('transaction_date')->orderBy('id'),
                'amount_desc' => $query->orderByDesc('amount'),
                'amount_asc' => $query->orderBy('amount'),
                default => $query->orderByDesc('transaction_date')->orderByDesc('id'),
            };

            $limit = $list_limit !== null ? max(1, min(50, (int) round($list_limit))) : 10;
            $transactions = $query->limit($limit)->get()->map(fn (Transaction $t) => [
                'id' => $t->id,
                'date' => $t->transaction_date->format('Y-m-d'),
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'type' => $t->transaction_type,
                'category' => $t->category?->name,
                'parent_category' => $t->category?->parent?->name,
                'account' => $t->account?->name,
                'payment_method' => $t->payment_method,
                'tags' => $t->tags,
            ])->values()->all();

            $total = match ($type) {
                'income' => $totals['income'],
                'expense' => $totals['expense'],
                default => $totals['net'],
            };

            return json_encode([
                'success' => true,
                'filters_applied' => array_filter([
                    'type' => $type,
                    'category_ids' => $category_ids,
                    'matched_categories' => $matchedCategories ?: null,
                    'from_date' => $from_date,
                    'to_date' => $to_date,
                    'description_search' => $description_search,
                    'account_name' => $account_name,
                    'payment_method' => $payment_method,
                    'tags' => $tags,
                    'sort_by' => $sort,
                ]),
                'total' => number_format($total, 2),
                'count' => $totals['count'],
                'income_total' => $type === 'both' ? number_format($totals['income'], 2) : null,
                'expense_total' => $type === 'both' ? number_format($totals['expense'], 2) : null,
                'net' => $type === 'both' ? number_format($totals['net'], 2) : null,
                'showing' => count($transactions).' of '.$totals['count'],
                'transactions' => $transactions,
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Search failed: '.$e->getMessage(),
            ]);
        }
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null; // ignore unparseable dates, as before
        }
    }
}
