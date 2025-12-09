<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Get today's income and expense statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function today()
    {
        $today = Carbon::today();

        $todayIncome = Transaction::where('transaction_type', 'income')
            ->whereDate('transaction_date', $today)
            ->sum('amount');

        $todayExpense = Transaction::where('transaction_type', 'expense')
            ->whereDate('transaction_date', $today)
            ->sum('amount');

        $todayTransactions = Transaction::whereDate('transaction_date', $today)->count();

        return response()->json([
            'date' => $today->format('Y-m-d'),
            'income' => (float) $todayIncome,
            'expense' => (float) $todayExpense,
            'net' => (float) ($todayIncome - $todayExpense),
            'transaction_count' => $todayTransactions,
        ]);
    }

    /**
     * Get current week's income and expense statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function weekly()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $weeklyIncome = Transaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startOfWeek, $endOfWeek])
            ->sum('amount');

        $weeklyExpense = Transaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startOfWeek, $endOfWeek])
            ->sum('amount');

        $weeklyTransactions = Transaction::whereBetween('transaction_date', [$startOfWeek, $endOfWeek])
            ->count();

        // Get daily breakdown
        $dailyBreakdown = [];
        for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
            $dayIncome = Transaction::where('transaction_type', 'income')
                ->whereDate('transaction_date', $date)
                ->sum('amount');

            $dayExpense = Transaction::where('transaction_type', 'expense')
                ->whereDate('transaction_date', $date)
                ->sum('amount');

            $dailyBreakdown[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('l'),
                'income' => (float) $dayIncome,
                'expense' => (float) $dayExpense,
                'net' => (float) ($dayIncome - $dayExpense),
            ];
        }

        return response()->json([
            'week_start' => $startOfWeek->format('Y-m-d'),
            'week_end' => $endOfWeek->format('Y-m-d'),
            'income' => (float) $weeklyIncome,
            'expense' => (float) $weeklyExpense,
            'net' => (float) ($weeklyIncome - $weeklyExpense),
            'transaction_count' => $weeklyTransactions,
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }

    /**
     * Get current month's income and expense statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthly()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyIncome = Transaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyExpense = Transaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyTransactions = Transaction::whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->count();

        return response()->json([
            'month' => $startOfMonth->format('F Y'),
            'month_start' => $startOfMonth->format('Y-m-d'),
            'month_end' => $endOfMonth->format('Y-m-d'),
            'income' => (float) $monthlyIncome,
            'expense' => (float) $monthlyExpense,
            'net' => (float) ($monthlyIncome - $monthlyExpense),
            'transaction_count' => $monthlyTransactions,
        ]);
    }

    /**
     * Get total current balance across all active accounts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance()
    {
        $accounts = Account::active()->get();

        $totalBalance = $accounts->sum('current_balance');

        $accountBreakdown = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'type' => $account->type,
                'current_balance' => (float) $account->current_balance,
                'bank_name' => $account->bank_name,
            ];
        });

        return response()->json([
            'total_balance' => (float) $totalBalance,
            'account_count' => $accounts->count(),
            'accounts' => $accountBreakdown,
        ]);
    }

    /**
     * Get comprehensive dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard()
    {
        // Overall stats
        $totalIncome = Transaction::where('transaction_type', 'income')->sum('amount');
        $totalExpense = Transaction::where('transaction_type', 'expense')->sum('amount');
        $totalBalance = Account::active()->sum('current_balance');

        // Today's stats
        $today = Carbon::today();
        $todayIncome = Transaction::where('transaction_type', 'income')
            ->whereDate('transaction_date', $today)
            ->sum('amount');
        $todayExpense = Transaction::where('transaction_type', 'expense')
            ->whereDate('transaction_date', $today)
            ->sum('amount');

        // This month's stats
        $startOfMonth = Carbon::now()->startOfMonth();
        $monthlyIncome = Transaction::where('transaction_type', 'income')
            ->whereDate('transaction_date', '>=', $startOfMonth)
            ->sum('amount');
        $monthlyExpense = Transaction::where('transaction_type', 'expense')
            ->whereDate('transaction_date', '>=', $startOfMonth)
            ->sum('amount');

        // Recent transactions
        $recentTransactions = Transaction::with(['category.parent', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'amount' => (float) $transaction->amount,
                    'type' => $transaction->transaction_type,
                    'date' => $transaction->transaction_date->format('Y-m-d'),
                    'payment_method' => $transaction->payment_method,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                    ] : null,
                    'account' => $transaction->account ? [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                    ] : null,
                ];
            });

        return response()->json([
            'overall' => [
                'total_income' => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'net_balance' => (float) ($totalIncome - $totalExpense),
                'current_balance' => (float) $totalBalance,
                'total_transactions' => Transaction::count(),
            ],
            'today' => [
                'income' => (float) $todayIncome,
                'expense' => (float) $todayExpense,
                'net' => (float) ($todayIncome - $todayExpense),
            ],
            'this_month' => [
                'income' => (float) $monthlyIncome,
                'expense' => (float) $monthlyExpense,
                'net' => (float) ($monthlyIncome - $monthlyExpense),
            ],
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Get custom date range statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dateRange(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $income = Transaction::where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $expense = Transaction::where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $transactionCount = Transaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->count();

        return response()->json([
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days' => $startDate->diffInDays($endDate) + 1,
            'income' => (float) $income,
            'expense' => (float) $expense,
            'net' => (float) ($income - $expense),
            'transaction_count' => $transactionCount,
            'average_daily_expense' => (float) ($expense / max(1, $startDate->diffInDays($endDate) + 1)),
        ]);
    }
}
