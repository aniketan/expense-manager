<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');

// Account routes
Route::resource('accounts', AccountController::class);
Route::patch('accounts/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');

// Category routes
Route::resource('categories', CategoryController::class);
Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');

// Transaction routes
Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
Route::resource('transactions', TransactionController::class);
Route::post('transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk-destroy');

// Budget routes
Route::resource('budgets', BudgetController::class);
Route::patch('budgets/{budget}/toggle-status', [BudgetController::class, 'toggleStatus'])->name('budgets.toggle-status');

// Dashboard routes
Route::redirect('/dashboard', '/')->name('dashboard.index');
Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');

// Chatbot routes (SSE + history)
Route::post('/chat/stream', [ChatController::class, 'stream'])->name('chat.stream');
Route::get('/chat/history', [ChatController::class, 'history'])->name('chat.history');
Route::post('/chat/reset', [ChatController::class, 'reset'])->name('chat.reset');

// AI categorization (statement review table)
Route::post('/ai/categorize', [AiController::class, 'categorize'])->name('ai.categorize');

// Bank statement import
Route::get('/statements/upload', [StatementController::class, 'uploadPage'])->name('statements.upload');
Route::get('/statements/review', [StatementController::class, 'review'])->name('statements.review');
Route::get('/statements/process', function () {
    if (session()->has('statement_review_snapshot')) {
        return redirect()->route('statements.review');
    }

    return redirect()->route('statements.upload');
});
Route::post('/statements/process', [StatementController::class, 'process'])->name('statements.process');
Route::post('/statements/import', [StatementController::class, 'importTransactions'])->name('statements.import');
Route::post('/statements/enrich', [StatementController::class, 'enrichTransactions'])->name('statements.enrich');
Route::post('/statements/bundle-link', [StatementController::class, 'bundleLink'])->name('statements.bundle-link');
