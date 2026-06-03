<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TransactionFilterService
{
    /**
     * Apply request filters to a transactions query. Returns metadata for the UI.
     * When cash_flow is credit or debit, it overrides transaction_type from the request.
     *
     * @return array<string, mixed>
     */
    public function applyToQuery(Request $request, Builder $query): array
    {
        $filters = [];

        if ($request->filled('search')) {
            $search = $request->get('search');
            $filters['search'] = $search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('subcategory')) {
            $subcategoryId = $request->get('subcategory');
            $filters['subcategory'] = $subcategoryId;
            $query->where('category_id', $subcategoryId);
        } elseif ($request->filled('category')) {
            $categoryId = $request->get('category');
            $filters['category'] = $categoryId;

            $subcategoryIds = Category::where('parent_id', $categoryId)->pluck('id')->toArray();

            if (! empty($subcategoryIds)) {
                $query->whereIn('category_id', $subcategoryIds);
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        if ($request->filled('account')) {
            $accountId = $request->get('account');
            $filters['account'] = $accountId;
            $query->where('account_id', $accountId);
        }

        if ($request->filled('date_from')) {
            $dateFrom = $request->get('date_from');
            $filters['date_from'] = $dateFrom;
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = $request->get('date_to');
            $filters['date_to'] = $dateTo;
            $query->where('transaction_date', '<=', $dateTo);
        }

        if ($request->filled('payment_method')) {
            $paymentMethod = $request->get('payment_method');
            $filters['payment_method'] = $paymentMethod;
            $query->where('payment_method', $paymentMethod);
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            $filters['status'] = $status;
            $query->where('status', $status);
        }

        $cashFlow = $request->get('cash_flow', 'all');
        if (in_array($cashFlow, ['credit', 'debit'], true)) {
            $filters['cash_flow'] = $cashFlow;
            if ($cashFlow === 'credit') {
                $query->where('transaction_type', 'income');
            } else {
                $query->whereIn('transaction_type', ['expense', 'transfer']);
            }
        } elseif ($request->filled('transaction_type')) {
            $transactionType = $request->get('transaction_type');
            $filters['transaction_type'] = $transactionType;
            $query->where('transaction_type', $transactionType);
        }

        return $filters;
    }

    /**
     * Base query with relations used for listing and export.
     */
    public static function baseQuery(): Builder
    {
        return Transaction::query()->with(['category.parent', 'account']);
    }
}
