<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use App\Services\TransactionFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionFilterService $filterService)
    {
        // Query strings arrive as strings ("25"), so cast before the strict whitelist check.
        $perPage = (int) $request->get('per_page', 15);
        if (! in_array($perPage, [15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $query = TransactionFilterService::baseQuery();
        $filters = $filterService->applyToQuery($request, $query);

        $filteredQuery = clone $query;

        $totalIncome = $filteredQuery->where('transaction_type', Transaction::TYPE_INCOME)->sum('amount');
        $totalExpenses = (clone $query)->where('transaction_type', Transaction::TYPE_EXPENSE)->sum('amount');
        $netBalance = $totalIncome - $totalExpenses;

        $totals = [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_balance' => $netBalance,
        ];

        $sortBy = $request->get('sort_by', 'date_desc');
        $filters['sort_by'] = $sortBy;

        switch ($sortBy) {
            case 'date_asc':
                $query->orderBy('transaction_date', 'asc');
                break;
            case 'amount_desc':
                $query->orderBy('amount', 'desc');
                break;
            case 'amount_asc':
                $query->orderBy('amount', 'asc');
                break;
            case 'category':
                // A subquery instead of a join: categories also has a `description`
                // column, which made the search filter ambiguous under a join.
                $query->orderBy(
                    Category::select('name')->whereColumn('categories.id', 'transactions.category_id')
                );
                break;
            case 'date_desc':
            default:
                $query->orderBy('transaction_date', 'desc');
                break;
        }

        // Tie-breaker so rows sharing a date or amount page deterministically.
        $query->orderBy('id', 'desc');

        $transactions = $query->paginate($perPage)->appends($request->query());

        $categories = Category::all();
        $accounts = Account::all();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => $categories,
            'accounts' => $accounts,
            'success' => session('success'),
            'error' => session('error'),
            'budget_alerts' => session('budget_alerts'),
            'filters' => $filters,
            'totals' => $totals,
        ]);
    }

    /**
     * Export filtered transactions as CSV (statement order: date ascending, then id).
     */
    public function export(Request $request, TransactionFilterService $filterService): StreamedResponse
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            if ($request->query('date_from') > $request->query('date_to')) {
                throw ValidationException::withMessages([
                    'date_from' => __('The from date must be before or equal to the to date.'),
                ]);
            }
        }

        $query = TransactionFilterService::baseQuery();
        $filterService->applyToQuery($request, $query);
        $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');

        $filename = 'transactions_export_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $writer = Writer::createFromStream($handle);
            $writer->insertOne([
                'Date', 'Account', 'Category', 'Subcategory', 'Type', 'Debit', 'Credit',
                'Description', 'Reference', 'Payment method', 'Tags', 'Payee/Payer',
                'Status', 'Tax', 'Notes', 'Location',
            ]);

            foreach ($query->lazy(500) as $transaction) {
                $writer->insertOne($this->csvRowForExport($transaction));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return list<string|numeric>
     */
    private function csvRowForExport(Transaction $transaction): array
    {
        $category = $transaction->category;
        if ($category && $category->parent_id) {
            $categoryName = $category->parent?->name ?? '';
            $subcategoryName = $category->name;
        } else {
            $categoryName = $category?->name ?? '';
            $subcategoryName = '';
        }

        $amount = (string) $transaction->amount;
        $debit = '';
        $credit = '';
        if ($transaction->getBalanceImpact() > 0) {
            $credit = $amount;
        } else {
            $debit = $amount;
        }

        return [
            $transaction->transaction_date->format('Y-m-d'),
            $transaction->account?->name ?? '',
            $categoryName,
            $subcategoryName,
            $transaction->transaction_type,
            $debit,
            $credit,
            $transaction->description ?? '',
            $transaction->reference_number ?? '',
            $transaction->payment_method ?? '',
            $transaction->tags ?? '',
            $transaction->payee_payer ?? '',
            $transaction->status ?? '',
            $transaction->tax ?? '',
            $transaction->notes ?? '',
            $transaction->location ?? '',
        ];
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create()
    {
        $categories = Category::all();
        $accounts = Account::all();

        return Inertia::render('Transactions/Create', [
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     *
     * Note: Account balance is automatically updated via Transaction model events
     * - Income transactions: Add to account balance
     * - Expense transactions: Subtract from account balance
     * - Transfer transactions: Creates two entries (outgoing + incoming)
     */
    public function store(Request $request, CreateTransaction $createTransaction)
    {
        $validated = $request->validate($this->validationRules());

        $createTransaction->handle($validated);

        if ($validated['transaction_type'] === Transaction::TYPE_TRANSFER) {
            return Redirect::route('transactions.index')
                ->with('success', 'Account transfer completed successfully.');
        }

        if ($validated['transaction_type'] === Transaction::TYPE_EXPENSE && isset($validated['category_id'])) {
            $budgetAlerts = $this->checkBudgetAlerts($validated['category_id'], $validated['transaction_date']);

            if (! empty($budgetAlerts)) {
                return Redirect::route('transactions.index')
                    ->with('success', 'Transaction created successfully.')
                    ->with('budget_alerts', $budgetAlerts);
            }
        }

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction created successfully.');
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load(['category.parent', 'account']),
        ]);
    }

    /**
     * Show the form for editing the specified transaction.
     */
    public function edit(Transaction $transaction, AccountTransferService $transferService)
    {
        if ($transferService->isUnlinked($transaction)) {
            return $this->unlinkedTransferRedirect();
        }

        if ($transaction->transaction_type === Transaction::TYPE_TRANSFER) {
            $legs = $transferService->pair($transaction);
            $transaction = $legs['outgoing'];
            $transaction->setAttribute('transfer_to_account_id', $legs['incoming']->account_id);
        }

        // Load the transaction with its relationships
        $transaction->load(['category.parent', 'account']);
        $transaction->transaction_time = $this->timeInputValue($transaction->transaction_time);

        $categories = Category::all();
        $accounts = Account::all();

        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction,
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Shared by store and update so the two forms cannot drift apart.
     *
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        // Input format only; domain rules (category/type compatibility, transfer pairing)
        // live in the shared transaction actions.
        return [
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|required_unless:transaction_type,transfer|exists:categories,id',
            'transaction_type' => ['required', Rule::in([Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE, Transaction::TYPE_TRANSFER])],
            'amount' => 'required|numeric|gt:0',
            'tax' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'payee_payer' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'transaction_date' => 'required|date',
            'transaction_time' => 'nullable|date_format:H:i',
            'status' => ['nullable', Rule::in(Transaction::STATUSES)],
            'payment_method' => 'nullable|required_unless:transaction_type,transfer|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'location' => 'nullable|string',
            'transfer_to_account_id' => 'nullable|required_if:transaction_type,transfer|different:account_id|exists:accounts,id',
        ];
    }

    private function unlinkedTransferRedirect()
    {
        return Redirect::route('transactions.index')
            ->with('error', 'This transfer has no linked counterpart (created before linked transfers existed) and cannot be edited. Delete it and record the transfer again.');
    }

    private function timeInputValue($value): ?string
    {
        if (! $value) {
            return null;
        }

        $stringValue = (string) $value;

        if (preg_match('/^(\\d{2}:\\d{2})(?::\\d{2})?$/', $stringValue, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(?:T|\\s)(\\d{2}:\\d{2})(?::\\d{2})?/', $stringValue, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Update the specified transaction in storage.
     *
     * Note: Account balance is automatically adjusted via Transaction model events
     * - If account changes: Old account is reverted, new account is updated
     * - If amount/type changes: Balance is recalculated accordingly
     */
    public function update(Request $request, Transaction $transaction, AccountTransferService $transferService, UpdateTransaction $updateTransaction)
    {
        if ($transferService->isUnlinked($transaction)) {
            return $this->unlinkedTransferRedirect();
        }

        $validated = $request->validate($this->validationRules());

        $updateTransaction->handle($transaction, $validated);

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction updated successfully.');
    }

    /**
     * Remove the specified transaction from storage.
     *
     * Note: Account balance is automatically reverted via Transaction model events
     * - The transaction's impact on the account balance is reversed
     */
    public function destroy(Transaction $transaction, DeleteTransaction $deleteTransaction)
    {
        $deleteTransaction->handle($transaction);

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Remove multiple transactions from storage.
     *
     * Note: Account balances are automatically reverted via Transaction model events
     * - Each transaction is deleted individually to trigger the model's deleted event
     */
    public function bulkDestroy(Request $request, DeleteTransaction $deleteTransaction)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:transactions,id',
        ]);

        // Fetch transactions individually and delete them to trigger model events
        $transactions = Transaction::whereIn('id', $validated['ids'])->get();
        $count = 0;

        $processedGroups = [];
        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === Transaction::TYPE_TRANSFER) {
                $groupId = $transaction->transfer_group_id;
                if ($groupId && isset($processedGroups[$groupId])) {
                    continue;
                }

                if ($groupId) {
                    $processedGroups[$groupId] = true;
                }
            }

            $deleteTransaction->handle($transaction);
            $count++;
        }

        return Redirect::route('transactions.index')
            ->with('success', "{$count} transaction(s) deleted successfully.");
    }

    /**
     * Check if any budgets are exceeded or approaching limit for the given category
     */
    private function checkBudgetAlerts($categoryId, $transactionDate)
    {
        $alerts = [];

        // Budgets on the parent category count child spending too, so they must alert as well.
        $categoryIds = array_filter([$categoryId, Category::whereKey($categoryId)->value('parent_id')]);

        $budgets = Budget::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $transactionDate)
            ->whereDate('end_date', '>=', $transactionDate)
            ->get();

        foreach ($budgets as $budget) {
            $percentage = $budget->percentage_used;

            if ($percentage >= 100) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "Budget '{$budget->name}' has been exceeded! {$percentage}% used (₹{$budget->spent_amount} / ₹{$budget->amount})",
                ];
            } elseif ($percentage >= 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Budget '{$budget->name}' is at {$percentage}% (₹{$budget->remaining_amount} remaining)",
                ];
            }
        }

        return $alerts;
    }
}
