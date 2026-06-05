<?php

namespace App\AI\Tools;

use Prism\Prism\Tool;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use Carbon\Carbon;

class CreateTransactionTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('create_transaction')
            ->for('Create a financial transaction when user mentions spending or receiving money')
            ->withNumberParameter('amount', 'The amount in rupees (e.g. 40, 500.50)')
            ->withStringParameter('description', 'What the money was spent on or received for')
            ->withEnumParameter('transaction_type', 'Either "income" or "expense"', ['income', 'expense'])
            ->withStringParameter('category_hint', 'Best matching category from context', false)
            ->withStringParameter('date', 'Date in YYYY-MM-DD format (optional, defaults to today)', false)
            ->withStringParameter('account_hint', 'Account name hint for matching (optional)', false)
            ->using($this->execute(...));
    }

    public function execute(
        float $amount,
        string $description,
        string $transaction_type,
        string $category_hint,
        ?string $date = null,
        ?string $account_hint = null,
    ): string {
        try {
            // Resolve account: match by name if hint provided, otherwise first active
            $account = null;
            if ($account_hint) {
                $account = Account::where('name', 'like', "%{$account_hint}%")
                    ->where('is_active', true)
                    ->first();
            }
            if (!$account) {
                $account = Account::where('is_active', true)->first();
            }

            if (!$account) {
                return json_encode([
                    'success' => false,
                    'error'   => 'No active account found. Please create an account first.',
                ]);
            }

            // Resolve category: match by name, fallback to "Other", fallback to any child category
            $category = Category::where('name', 'like', "%{$category_hint}%")
                ->whereNotNull('parent_id')
                ->first();

            if (!$category) {
                $category = Category::where('name', 'Other')
                    ->whereNotNull('parent_id')
                    ->first();
            }

            if (!$category) {
                $category = Category::whereNotNull('parent_id')->first();
            }

            if (!$category) {
                return json_encode([
                    'success' => false,
                    'error'   => 'No categories found. Please set up expense categories first.',
                ]);
            }

            // Validate transaction type
            if (!in_array($transaction_type, ['income', 'expense', 'transfer'])) {
                return json_encode([
                    'success' => false,
                    'error'   => 'Invalid transaction type. Must be "income" or "expense".',
                ]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'account_id'       => $account->id,
                'category_id'      => $category->id,
                'transaction_type' => $transaction_type,
                'amount'           => abs($amount),
                'description'      => $description,
                'transaction_date' => $date ? Carbon::createFromFormat('Y-m-d', $date)->toDateString() : Carbon::today()->toDateString(),
                'payment_method'   => 'Other',
            ]);

            return json_encode([
                'success'    => true,
                'id'         => $transaction->id,
                'message'    => "✅ Created {$transaction_type} of ₹{$amount} for \"{$description}\"",
                'account'    => $account->name,
                'category'   => $category->name,
                'date'       => $transaction->transaction_date->format('Y-m-d'),
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'Failed to create transaction: ' . $e->getMessage(),
            ]);
        }
    }
}
