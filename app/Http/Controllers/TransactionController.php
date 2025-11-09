<?php

namespace App\Http\Controllers;
use App\Models\Transaction;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        // Validate per_page parameter
        if (!in_array($perPage, [15, 25, 50, 100])) {
            $perPage = 15;
        }

        // Start building the query
        $query = Transaction::with(['category.parent', 'account']);

        // Apply filters
        $filters = [];

        // Search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $filters['search'] = $search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('category', function($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Category and Subcategory filtering
        if ($request->filled('subcategory')) {
            // If subcategory is specified, filter by specific subcategory
            $subcategoryId = $request->get('subcategory');
            $filters['subcategory'] = $subcategoryId;
            $query->where('category_id', $subcategoryId);
        } elseif ($request->filled('category')) {
            // If only parent category is specified, filter by all subcategories under this parent
            $categoryId = $request->get('category');
            $filters['category'] = $categoryId;

            // Get all subcategory IDs for this parent category
            $subcategoryIds = \App\Models\Category::where('parent_id', $categoryId)->pluck('id')->toArray();

            if (!empty($subcategoryIds)) {
                $query->whereIn('category_id', $subcategoryIds);
            } else {
                // If no subcategories exist, filter by the parent category itself
                $query->where('category_id', $categoryId);
            }
        }

        // Account filter
        if ($request->filled('account')) {
            $accountId = $request->get('account');
            $filters['account'] = $accountId;
            $query->where('account_id', $accountId);
        }

        // Date range filters
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

        // Payment method filter
        if ($request->filled('payment_method')) {
            $paymentMethod = $request->get('payment_method');
            $filters['payment_method'] = $paymentMethod;
            $query->where('payment_method', $paymentMethod);
        }

        // Status filter
        if ($request->filled('status')) {
            $status = $request->get('status');
            $filters['status'] = $status;
            $query->where('status', $status);
        }

        // Transaction type filter
        if ($request->filled('transaction_type')) {
            $transactionType = $request->get('transaction_type');
            $filters['transaction_type'] = $transactionType;
            $query->where('transaction_type', $transactionType);
        }

        // Calculate totals based on filtered data (before pagination)
        $filteredQuery = clone $query;

        $totalIncome = $filteredQuery->where('transaction_type', 'income')->sum('amount');
        $totalExpenses = (clone $query)->where('transaction_type', '!=', 'income')->sum('amount');
        $netBalance = $totalIncome - $totalExpenses;

        $totals = [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_balance' => $netBalance
        ];

        // Apply sorting
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
                $query->join('categories', 'transactions.category_id', '=', 'categories.id')
                      ->orderBy('categories.name', 'asc')
                      ->select('transactions.*');
                break;
            case 'date_desc':
            default:
                $query->orderBy('transaction_date', 'desc');
                break;
        }

        $transactions = $query->paginate($perPage)->appends($request->query());

        $categories = \App\Models\Category::all();
        $accounts = \App\Models\Account::all();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => $categories,
            'accounts' => $accounts,
            'success' => session('success'),
            'filters' => $filters,
            'totals' => $totals,
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create()
    {
       $categories = \App\Models\Category::all();
       $accounts = \App\Models\Account::all();

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'transaction_type' => 'required|string|max:20',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'location' => 'nullable|string',
            'transfer_to_account_id' => 'nullable|required_if:transaction_type,transfer|exists:accounts,id',
        ]);

        // Handle account transfer - create two transactions
        if ($validated['transaction_type'] === 'transfer') {
            // Get the account transfer category
            $transferCategory = \App\Models\Category::where('code', 'ACCOUNTTR')->first();

            if (!$transferCategory) {
                return Redirect::back()
                    ->withErrors(['transfer' => 'Account transfer category not found. Please contact administrator.']);
            }

            // Get a subcategory under ACCOUNTTR or use the parent if no subcategories exist
            $transferSubcategory = \App\Models\Category::where('parent_id', $transferCategory->id)->first();
            $categoryId = $transferSubcategory ? $transferSubcategory->id : $transferCategory->id;

            // Create outgoing transaction (from source account)
            Transaction::create([
                'account_id' => $validated['account_id'],
                'category_id' => $categoryId,
                'transaction_type' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Transfer to account',
                'transaction_date' => $validated['transaction_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'location' => $validated['location'] ?? null,
            ]);

            // Get income category for the incoming transaction
            $incomeCategory = \App\Models\Category::where('code', 'INCOME')->first();

            if (!$incomeCategory) {
                return Redirect::back()
                    ->withErrors(['transfer' => 'Income category not found. Please contact administrator.']);
            }

            // Get a subcategory under INCOME or use the parent if no subcategories exist
            $incomeSubcategory = \App\Models\Category::where('parent_id', $incomeCategory->id)->first();
            $incomeCategoryId = $incomeSubcategory ? $incomeSubcategory->id : $incomeCategory->id;

            // Create incoming transaction (to destination account)
            Transaction::create([
                'account_id' => $validated['transfer_to_account_id'],
                'category_id' => $incomeCategoryId,
                'transaction_type' => 'income',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Transfer from account',
                'transaction_date' => $validated['transaction_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'location' => $validated['location'] ?? null,
            ]);

            return Redirect::route('transactions.index')
                ->with('success', 'Account transfer completed successfully.');
        }

        // Regular transaction (income or expense)
        Transaction::create($validated);

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
    public function edit(Transaction $transaction)
    {
        // Load the transaction with its relationships
        $transaction->load(['category.parent', 'account']);

        $categories = \App\Models\Category::all();
        $accounts = \App\Models\Account::all();

        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction,
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update the specified transaction in storage.
     *
     * Note: Account balance is automatically adjusted via Transaction model events
     * - If account changes: Old account is reverted, new account is updated
     * - If amount/type changes: Balance is recalculated accordingly
     */
    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'required|exists:categories,id',
            'transaction_type' => 'nullable|string|max:20',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'expensed_date' => 'required|date',
            'transaction_time' => 'nullable|string',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'payee_payer' => 'nullable|string',
            'tax' => 'nullable|numeric',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Map expensed_date to transaction_date for database compatibility
        if (isset($validated['expensed_date'])) {
            $validated['transaction_date'] = $validated['expensed_date'];
            unset($validated['expensed_date']);
        }

        $transaction->update($validated);

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction updated successfully.');
    }

    /**
     * Remove the specified transaction from storage.
     *
     * Note: Account balance is automatically reverted via Transaction model events
     * - The transaction's impact on the account balance is reversed
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Remove multiple transactions from storage.
     *
     * Note: Account balances are automatically reverted via Transaction model events
     * - Each transaction is deleted individually to trigger the model's deleted event
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:transactions,id',
        ]);

        // Fetch transactions individually and delete them to trigger model events
        $transactions = Transaction::whereIn('id', $validated['ids'])->get();
        $count = 0;

        foreach ($transactions as $transaction) {
            $transaction->delete();
            $count++;
        }

        return Redirect::route('transactions.index')
            ->with('success', "{$count} transaction(s) deleted successfully.");
    }

    /**
     * Get dashboard statistics for the home page
     */
    public function getDashboardStats()
    {
        // Get total income (where transaction_type is 'income')
        $totalIncome = Transaction::where('transaction_type', 'income')
            ->sum('amount');

        // Get total expenses (where transaction_type is not 'income')
        $totalExpenses = Transaction::where('transaction_type', '!=', 'income')
            ->sum('amount');

        // Convert negative expenses to positive for display
        $totalExpenses = abs($totalExpenses);

        // Calculate net balance
        $netBalance = $totalIncome - $totalExpenses;

        // Get total transaction count
        $totalTransactions = Transaction::count();

        return [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netBalance' => $netBalance,
            'totalTransactions' => $totalTransactions
        ];
    }

    /**
     * Get recent transactions for the home page
     */
    public function getRecentTransactions($limit = 10)
    {
        return Transaction::with(['category.parent', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
