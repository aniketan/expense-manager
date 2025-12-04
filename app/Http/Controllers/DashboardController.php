<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Account;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $recentTransactions = $this->getRecentTransactions();
        $chartData = $this->getChartData();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Show analytics page with charts
     */
    public function analytics()
    {
        $chartData = [
            'monthlyTrend' => $this->getMonthlyTrendData(),
            'monthlyComparison' => $this->getMonthlyComparison(),
            'categoryBreakdown' => $this->getCategoryBreakdown(),
            'accountBreakdown' => $this->getAccountBreakdown(),
            'transactionTypeDistribution' => $this->getTransactionTypeDistribution(),
        ];

        return Inertia::render('Dashboard/Analytics', ['chartData' => $chartData]);
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats()
    {
        // Get total income (where transaction_type is 'income')
        $totalIncome = Transaction::where('transaction_type', 'income')
            ->sum('amount');

        // Get total expenses (where transaction_type is 'expense')
        $totalExpenses = Transaction::where('transaction_type', 'expense')
            ->sum('amount');

        // Calculate net balance
        $netBalance = $totalIncome - $totalExpenses;

        // Get total transaction count
        $totalTransactions = Transaction::count();

        // Get total accounts
        $totalAccounts = Account::active()->count();

        // Get current month income and expenses
        $currentMonth = Carbon::now();
        $monthlyIncome = Transaction::where('transaction_type', 'income')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        $monthlyExpenses = Transaction::where('transaction_type', 'expense')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        return [
            'totalIncome' => (float) $totalIncome,
            'totalExpenses' => (float) $totalExpenses,
            'netBalance' => (float) $netBalance,
            'totalTransactions' => $totalTransactions,
            'totalAccounts' => $totalAccounts,
            'monthlyIncome' => (float) $monthlyIncome,
            'monthlyExpenses' => (float) $monthlyExpenses,
            'monthlyNetBalance' => (float) ($monthlyIncome - $monthlyExpenses),
        ];
    }

    /**
     * Get recent transactions for the dashboard
     */
    private function getRecentTransactions($limit = 10)
    {
        return Transaction::with(['category.parent', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'description' => $transaction->description,
                    'amount' => (float) $transaction->amount,
                    'type' => $transaction->transaction_type,
                    'date' => $transaction->transaction_date->format('Y-m-d'),
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'full_name' => $transaction->category->full_name,
                    ] : null,
                    'account' => $transaction->account ? [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                    ] : null,
                ];
            });
    }

    /**
     * Get monthly trend data for line chart
     * Returns data for the last 12 months
     */
    private function getMonthlyTrendData()
    {
        $months = [];
        $data = [];

        // Get data for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');

            $income = Transaction::where('transaction_type', 'income')
                ->whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->sum('amount');

            $expense = Transaction::where('transaction_type', 'expense')
                ->whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->sum('amount');

            $balance = $income - $expense;

            $data[] = [
                'month' => $monthName,
                'income' => (float) $income,
                'expense' => (float) $expense,
                'balance' => (float) $balance,
            ];
        }

        return $data;
    }

    /**
     * Get monthly comparison data for bar chart
     * Returns current month vs previous month
     */
    private function getMonthlyComparison()
    {
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        // Current month
        $currentIncome = Transaction::where('transaction_type', 'income')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        $currentExpense = Transaction::where('transaction_type', 'expense')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        // Previous month
        $previousIncome = Transaction::where('transaction_type', 'income')
            ->whereYear('transaction_date', $previousMonth->year)
            ->whereMonth('transaction_date', $previousMonth->month)
            ->sum('amount');

        $previousExpense = Transaction::where('transaction_type', 'expense')
            ->whereYear('transaction_date', $previousMonth->year)
            ->whereMonth('transaction_date', $previousMonth->month)
            ->sum('amount');

        return [
            [
                'name' => $currentMonth->format('M Y'),
                'income' => (float) $currentIncome,
                'expense' => (float) $currentExpense,
            ],
            [
                'name' => $previousMonth->format('M Y'),
                'income' => (float) $previousIncome,
                'expense' => (float) $previousExpense,
            ],
        ];
    }

    /**
     * Get category breakdown data for pie chart
     * Shows expenses by category for current month
     */
    private function getCategoryBreakdown()
    {
        $currentMonth = Carbon::now();

        $categoryData = Transaction::where('transaction_type', 'expense')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->with('category')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                $categoryName = $item->category
                    ? ($item->category->parent
                        ? $item->category->parent->name . ' > ' . $item->category->name
                        : $item->category->name)
                    : 'Uncategorized';

                return [
                    'name' => $categoryName,
                    'value' => (float) $item->total,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();

        return array_slice($categoryData, 0, 10); // Top 10 categories
    }

    /**
     * Get account breakdown data
     * Shows balance distribution across accounts
     */
    private function getAccountBreakdown()
    {
        return Account::active()
            ->get()
            ->map(function ($account) {
                return [
                    'name' => $account->name,
                    'type' => $account->type,
                    'balance' => (float) $account->current_balance,
                ];
            })
            ->all();
    }

    /**
     * Get transaction type distribution
     * Shows pie chart of income vs expense ratio
     */
    private function getTransactionTypeDistribution()
    {
        $currentMonth = Carbon::now();

        $income = Transaction::where('transaction_type', 'income')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        $expense = Transaction::where('transaction_type', 'expense')
            ->whereYear('transaction_date', $currentMonth->year)
            ->whereMonth('transaction_date', $currentMonth->month)
            ->sum('amount');

        return [
            [
                'name' => 'Income',
                'value' => (float) $income,
            ],
            [
                'name' => 'Expense',
                'value' => (float) $expense,
            ],
        ];
    }

    /**
     * Get all chart data
     */
    private function getChartData()
    {
        return [
            'monthlyTrend' => $this->getMonthlyTrendData(),
            'monthlyComparison' => $this->getMonthlyComparison(),
            'categoryBreakdown' => $this->getCategoryBreakdown(),
        ];
    }
}
