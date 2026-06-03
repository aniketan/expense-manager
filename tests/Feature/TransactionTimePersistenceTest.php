<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTimePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transaction_persists_transaction_time(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();

        $response = $this->post('/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '125.50',
            'description' => 'Evening groceries',
            'transaction_date' => '2026-06-03',
            'transaction_time' => '18:45',
            'payment_method' => 'UPI',
        ]);

        $response->assertRedirect('/transactions');

        $transaction = Transaction::query()->where('description', 'Evening groceries')->firstOrFail();

        $this->assertSame('18:45', substr((string) $transaction->transaction_time, 0, 5));
    }

    public function test_update_transaction_persists_transaction_time(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();

        $transaction = Transaction::withoutEvents(fn () => Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '50.00',
            'description' => 'Lunch',
            'transaction_date' => '2026-06-02',
            'transaction_time' => '12:10',
            'payment_method' => 'Cash',
        ]));

        $response = $this->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => '75.00',
            'description' => 'Updated lunch',
            'expensed_date' => '2026-06-03',
            'transaction_time' => '13:25',
            'payment_method' => 'Cash',
        ]);

        $response->assertRedirect('/transactions');

        $transaction->refresh();

        $this->assertSame('13:25', substr((string) $transaction->transaction_time, 0, 5));
    }
}
