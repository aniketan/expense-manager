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
        
        $transactions = Transaction::with(['category', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage); // Use dynamic per page value
        
        $categories = \App\Models\Category::where('parent_id', null)->get();
        $accounts = \App\Models\Account::all();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => $categories,
            'accounts' => $accounts,
            'success' => session('success'),
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
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'required|exists:categories,id',
            'transaction_type' => 'required|string|max:20',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

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
            'transaction' => $transaction->load(['category', 'account']),
        ]);
    }

    /**
     * Show the form for editing the specified transaction.
     */
    public function edit(Transaction $transaction)
    {
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
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Get dashboard statistics for the home page
     */
    public function getDashboardStats()
    {
        // Get total income (where transaction_type is 'income' or amount is positive)
        $totalIncome = Transaction::where('transaction_type', 'income')
            ->orWhere('amount', '>', 0)
            ->sum('amount');

        // Get total expenses (where transaction_type is 'expense' or amount is negative)
        $totalExpenses = Transaction::where('transaction_type', 'expense')
            ->orWhere('amount', '<', 0)
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
        return Transaction::with(['category', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}