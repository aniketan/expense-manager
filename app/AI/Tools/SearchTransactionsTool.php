<?php

namespace App\AI\Tools;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Prism\Prism\Tool;

class SearchTransactionsTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('search_transactions')
            ->for(
                'Search and filter transactions with full control over category, account, date range, description, payment method, and tags. ' .
                'Use after list_categories to get real category IDs. ' .
                'All parameters are optional except type. ' .
                'Use list_limit (1–50) to control how many rows are returned. ' .
                'Returns matching transactions plus aggregate totals and the matched category names.'
            )
            ->withEnumParameter(
                'type',
                'Filter by transaction type',
                ['expense', 'income', 'both']
            )
            ->withStringParameter(
                'category_ids',
                'Comma-separated category IDs obtained from list_categories (e.g. "5,12,18"). Leave empty to search all categories.',
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
                'Keyword to search inside transaction description/notes (case-insensitive partial match).',
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
                ['UPI', 'cash', 'card', 'netbanking', 'other'],
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
        string  $type,
        ?string $category_ids      = null,
        ?string $from_date         = null,
        ?string $to_date           = null,
        ?string $description_search = null,
        ?string $account_name      = null,
        ?string $payment_method    = null,
        ?string $tags              = null,
        ?float  $list_limit        = null,
        ?string $sort_by           = null,
    ): string {
        try {
            $query = Transaction::with(['category.parent', 'account']);

            // Transaction type filter
            if ($type !== 'both') {
                $query->where('transaction_type', $type);
            }

            // Category filter via comma-separated IDs
            $matchedCategories = [];
            if (!empty($category_ids)) {
                $ids = array_filter(array_map('intval', explode(',', $category_ids)));
                if (!empty($ids)) {
                    $query->whereIn('category_id', $ids);
                    $matchedCategories = Category::whereIn('id', $ids)
                        ->pluck('name')
                        ->values()
                        ->all();
                }
            }

            // Date range filters
            if (!empty($from_date)) {
                try {
                    $query->whereDate('transaction_date', '>=', Carbon::parse($from_date)->toDateString());
                } catch (\Throwable) {
                    // ignore unparseable date
                }
            }
            if (!empty($to_date)) {
                try {
                    $query->whereDate('transaction_date', '<=', Carbon::parse($to_date)->toDateString());
                } catch (\Throwable) {
                    // ignore unparseable date
                }
            }

            // Description keyword search
            if (!empty($description_search)) {
                $query->where('description', 'like', '%' . $description_search . '%');
            }

            // Account name filter
            if (!empty($account_name)) {
                $accountIds = Account::where('name', 'like', '%' . $account_name . '%')
                    ->pluck('id');
                $query->whereIn('account_id', $accountIds);
            }

            // Payment method filter
            if (!empty($payment_method)) {
                $query->where('payment_method', $payment_method);
            }

            // Tags keyword search
            if (!empty($tags)) {
                $query->where('tags', 'like', '%' . $tags . '%');
            }

            // Aggregates (on filtered base query before limit/sort)
            $total   = (clone $query)->sum('amount');
            $count   = (clone $query)->count();
            $income  = $type === 'both' ? (clone $query)->where('transaction_type', 'income')->sum('amount') : null;
            $expense = $type === 'both' ? (clone $query)->where('transaction_type', 'expense')->sum('amount') : null;

            // Sort
            $sort = $sort_by ?? 'newest';
            match ($sort) {
                'oldest'      => $query->orderBy('transaction_date')->orderBy('id'),
                'amount_desc' => $query->orderByDesc('amount'),
                'amount_asc'  => $query->orderBy('amount'),
                default       => $query->orderByDesc('transaction_date')->orderByDesc('id'),
            };

            // Limit rows returned
            $limit = $list_limit !== null ? max(1, min(50, (int) round($list_limit))) : 10;
            $rows  = $query->limit($limit)->get();

            $transactions = $rows->map(fn (Transaction $t) => [
                'id'             => $t->id,
                'date'           => $t->transaction_date->format('Y-m-d'),
                'description'    => $t->description,
                'amount'         => (float) $t->amount,
                'type'           => $t->transaction_type,
                'category'       => $t->category?->name,
                'parent_category'=> $t->category?->parent?->name,
                'account'        => $t->account?->name,
                'payment_method' => $t->payment_method,
                'tags'           => $t->tags,
            ])->values()->all();

            return json_encode([
                'success'           => true,
                'filters_applied'   => array_filter([
                    'type'               => $type,
                    'category_ids'       => $category_ids,
                    'matched_categories' => $matchedCategories ?: null,
                    'from_date'          => $from_date,
                    'to_date'            => $to_date,
                    'description_search' => $description_search,
                    'account_name'       => $account_name,
                    'payment_method'     => $payment_method,
                    'tags'               => $tags,
                    'sort_by'            => $sort,
                ]),
                'total'             => number_format((float) $total, 2),
                'count'             => $count,
                'income_total'      => $income !== null ? number_format((float) $income, 2) : null,
                'expense_total'     => $expense !== null ? number_format((float) $expense, 2) : null,
                'net'               => ($income !== null && $expense !== null)
                                        ? number_format((float) $income - (float) $expense, 2)
                                        : null,
                'showing'          => count($transactions) . ' of ' . $count,
                'transactions'      => $transactions,
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'Search failed: ' . $e->getMessage(),
            ]);
        }
    }
}
