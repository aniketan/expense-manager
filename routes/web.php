<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\StatsController;

Route::get('/', function () {
    $transactionController = new \App\Http\Controllers\TransactionController();

    // Get dashboard statistics
    $stats = $transactionController->getDashboardStats();

    // Get recent transactions
    $recentTransactions = $transactionController->getRecentTransactions(10);

    return \Inertia\Inertia::render('Welcome', [
        'stats' => $stats,
        'recentTransactions' => $recentTransactions
    ]);
});

// Account routes
Route::resource('accounts', AccountController::class);
Route::patch('accounts/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');
Route::get('api/accounts', [AccountController::class, 'getAccounts'])->name('api.accounts');

// Category routes
Route::resource('categories', CategoryController::class);
Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
Route::get('api/categories', [CategoryController::class, 'getCategories'])->name('api.categories');
Route::get('api/categories/parents', [CategoryController::class, 'getParentCategories'])->name('api.categories.parents');
Route::get('api/categories/{category}/children', [CategoryController::class, 'getChildCategories'])->name('api.categories.children');
Route::get('api/categories/with-totals', [CategoryController::class, 'getCategoriesWithTotals'])->name('api.categories.with-totals');

// Transaction routes
Route::resource('transactions', TransactionController::class);
Route::post('transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk-destroy');
Route::get('api/transactions', [TransactionController::class, 'getTransactions'])->name('api.transactions');

// Dashboard routes
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');

// API Routes for Statistics (useful for mobile apps)
Route::prefix('api/stats')->name('api.stats.')->group(function () {
    Route::get('today', [StatsController::class, 'today'])->name('today');
    Route::get('weekly', [StatsController::class, 'weekly'])->name('weekly');
    Route::get('monthly', [StatsController::class, 'monthly'])->name('monthly');
    Route::get('balance', [StatsController::class, 'balance'])->name('balance');
    Route::get('dashboard', [StatsController::class, 'dashboard'])->name('dashboard');
    Route::get('date-range', [StatsController::class, 'dateRange'])->name('date-range');
});
