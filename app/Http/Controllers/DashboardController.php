<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Reporting\FinancialSummary;
use App\Reporting\TransactionFilters;
use Carbon\CarbonImmutable;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private readonly FinancialSummary $summary) {}

    /**
     * Home page: headline totals and the latest transactions.
     */
    public function index()
    {
        $totals = $this->summary->totals(TransactionFilters::all());

        return Inertia::render('Welcome', [
            'stats' => [
                'totalIncome' => $totals['income'],
                'totalExpenses' => $totals['expense'],
                'netBalance' => $totals['net'],
                'totalTransactions' => $totals['count'],
            ],
            'recentTransactions' => Transaction::with(['category.parent', 'account'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Show analytics page with charts
     */
    public function analytics()
    {
        $thisMonth = TransactionFilters::all()->forPeriod('this_month');
        $current = $this->summary->totals($thisMonth);
        $previous = $this->summary->totals(TransactionFilters::all()->forPeriod('last_month'));
        $now = CarbonImmutable::now();

        $chartData = [
            'monthlyTrend' => $this->summary->monthlyTrend(12),
            'monthlyComparison' => [
                ['name' => $now->format('M Y'), 'income' => $current['income'], 'expense' => $current['expense']],
                [
                    'name' => $now->startOfMonth()->subMonthNoOverflow()->format('M Y'),
                    'income' => $previous['income'],
                    'expense' => $previous['expense'],
                ],
            ],
            'categoryBreakdown' => $this->summary
                ->topCategories($thisMonth->ofType(Transaction::TYPE_EXPENSE), 10)
                ->map(fn (array $row) => ['name' => $row['full_name'], 'value' => $row['total']])
                ->all(),
            'accountBreakdown' => Account::active()
                ->get()
                ->map(fn (Account $account) => [
                    'name' => $account->name,
                    'type' => $account->type,
                    'balance' => (float) $account->current_balance,
                ])
                ->all(),
            'transactionTypeDistribution' => [
                ['name' => 'Income', 'value' => $current['income']],
                ['name' => 'Expense', 'value' => $current['expense']],
            ],
        ];

        return Inertia::render('Dashboard/Analytics', ['chartData' => $chartData]);
    }
}
