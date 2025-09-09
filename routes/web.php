<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;

Route::get('/', function () {
    return \Inertia\Inertia::render('Welcome');
});

// Account routes
Route::resource('accounts', AccountController::class);
Route::patch('accounts/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');
Route::get('api/accounts', [AccountController::class, 'getAccounts'])->name('api.accounts');
