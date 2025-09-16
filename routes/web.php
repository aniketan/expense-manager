<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;

Route::get('/', function () {
    return \Inertia\Inertia::render('Welcome');
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
