<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Display a listing of the accounts.
     */
    public function index()
    {
        $accounts = Account::orderBy('name')->get();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * Show the form for creating a new account.
     */
    public function create()
    {
        return Inertia::render('Accounts/Create', [
            'accountTypes' => Account::getTypes(),
        ]);
    }

    /**
     * Store a newly created account in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:accounts,code',
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:20|in:'.implode(',', array_keys(Account::getTypes())),
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            // Negative openings are valid, e.g. a credit card that already carries dues.
            'opening_balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        // Set defaults
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        // A new account has no transactions yet, so its balance is its opening balance.
        $validated['current_balance'] = $validated['opening_balance'];
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        Account::create($validated);

        return Redirect::route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    /**
     * Display the specified account.
     */
    public function show(Account $account)
    {
        return Inertia::render('Accounts/Show', [
            'account' => $account->load([]), // Add relationships here if needed
        ]);
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit(Account $account)
    {
        return Inertia::render('Accounts/Edit', [
            'account' => $account,
            'accountTypes' => Account::getTypes(),
        ]);
    }

    /**
     * Update the specified account in storage.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:accounts,code,'.$account->id,
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:20|in:'.implode(',', array_keys(Account::getTypes())),
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            // Negative openings are valid, e.g. a credit card that already carries dues.
            'opening_balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        // current_balance is always opening balance + transactions; it is never edited directly,
        // so moving the opening balance shifts the current balance by the same amount.
        $validated['opening_balance'] = $validated['opening_balance'] ?? $account->opening_balance;
        $validated['current_balance'] = (float) $account->current_balance
            + ((float) $validated['opening_balance'] - (float) $account->opening_balance);

        $account->update($validated);

        return Redirect::route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Remove the specified account from storage.
     */
    public function destroy(Account $account)
    {
        // Database cascades bypass Transaction model events, which would strand the
        // other leg of any transfer and leave the counterpart account balance wrong.
        if ($account->transactions()->exists()) {
            return Redirect::route('accounts.index')
                ->with('error', 'Cannot delete account. Reassign or delete its transactions first, or deactivate the account instead.');
        }

        try {
            $account->delete();

            return Redirect::route('accounts.index')
                ->with('success', 'Account deleted successfully.');
        } catch (\Exception $e) {
            return Redirect::route('accounts.index')
                ->with('error', 'Cannot delete account. It may be associated with transactions.');
        }
    }

    /**
     * Toggle account status (active/inactive).
     */
    public function toggleStatus(Account $account)
    {
        $account->update(['is_active' => ! $account->is_active]);

        $status = $account->is_active ? 'activated' : 'deactivated';

        return Redirect::route('accounts.index')
            ->with('success', "Account {$status} successfully.");
    }
}
