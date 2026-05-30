<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_receives_transaction_time_in_time_input_format(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 125.50,
            'description' => 'Lunch',
            'transaction_date' => '2026-05-30',
            'transaction_time' => '14:30:00',
            'payment_method' => 'UPI',
        ]);

        $this->get(route('transactions.edit', $transaction))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Edit', false)
                ->where('transaction.id', $transaction->id)
                ->where('transaction.transaction_time', '14:30')
            );
    }

    public function test_edit_form_leaves_missing_transaction_time_empty(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 125.50,
            'description' => 'Lunch',
            'transaction_date' => '2026-05-30',
            'transaction_time' => null,
            'payment_method' => 'UPI',
        ]);

        $this->get(route('transactions.edit', $transaction))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Edit', false)
                ->where('transaction.id', $transaction->id)
                ->where('transaction.transaction_time', null)
            );
    }
}
