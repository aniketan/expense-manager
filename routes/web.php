<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;

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