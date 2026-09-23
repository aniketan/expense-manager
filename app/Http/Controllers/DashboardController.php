<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Home page: headline totals and the latest transactions.
     */
    public function index()
    {
        return Inertia::render('Welcome', [
            'stats' => $this->getDashboardStats(),
            'recentTransactions' => $this->getRecentTransactions(10),
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
     * Transfers only move money between accounts, so they are excluded from income and expenses.
     */
    private function getDashboardStats(): array
    {
        $totalIncome = Transaction::where('transaction_type', Transaction::TYPE_INCOME)->sum('amount');
        $totalExpenses = Transaction::where('transaction_type', Transaction::TYPE_EXPENSE)->sum('amount');

        return [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netBalance' => $totalIncome - $totalExpenses,
            'totalTransactions' => Transaction::count(),
        ];
    }

    private function getRecentTransactions(int $limit)
    {
        return Transaction::with(['category.parent', 'account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
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
                        ? $item->category->parent->name.' > '.$item->category->name
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
}
