<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupeTransactionsByDescriptionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_removes_duplicates_keeping_oldest(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['is_active' => true]);

        $txn1 = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 10,
            'description' => '  TXN-123  ',
            'transaction_date' => '2026-01-10',
            'payment_method' => 'Bank Transfer',
        ]);
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 10,
            'description' => 'TXN-123',
            'transaction_date' => '2026-01-10',
            'payment_method' => 'Bank Transfer',
        ]);
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'description' => 'other',
            'transaction_date' => '2026-03-10',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->artisan('transactions:dedupe-by-description', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(2, Transaction::query()->count());
        $this->assertNotNull(Transaction::query()->find($txn1->id));
        $this->assertSame('  TXN-123  ', Transaction::query()->find($txn1->id)?->description);
    }

    public function test_command_keeps_newest_when_option_set(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['is_active' => true]);

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 2,
            'description' => 'SAME',
            'transaction_date' => '2026-01-02',
            'payment_method' => 'Bank Transfer',
        ]);
        $latest = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 2,
            'description' => 'SAME',
            'transaction_date' => '2026-01-02',
            'payment_method' => 'Bank Transfer',
        ]);

        $this->artisan('transactions:dedupe-by-description', ['--keep' => 'newest', '--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame($latest->id, Transaction::query()->first()->id);
    }

    public function test_command_rejects_invalid_keep_option(): void
    {
        $this->artisan('transactions:dedupe-by-description', ['--keep' => 'middle'])
            ->assertFailed();
    }

    public function test_command_does_not_delete_same_description_when_core_fields_differ(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['is_active' => true]);
        $otherAccount = Account::factory()->create(['is_active' => true]);
        foreach ([['account' => $account->id, 'date' => '2026-01-10', 'type' => 'expense', 'amount' => 10], ['account' => $account->id, 'date' => '2026-01-10', 'type' => 'expense', 'amount' => 11], ['account' => $account->id, 'date' => '2026-01-11', 'type' => 'expense', 'amount' => 10], ['account' => $otherAccount->id, 'date' => '2026-01-10', 'type' => 'expense', 'amount' => 10], ['account' => $account->id, 'date' => '2026-01-10', 'type' => 'income', 'amount' => 10]] as $row) {
            Transaction::create(['account_id' => $row['account'], 'category_id' => $child->id, 'transaction_type' => $row['type'], 'amount' => $row['amount'], 'description' => 'SAME-DESC', 'transaction_date' => $row['date'], 'payment_method' => 'Bank Transfer']);
        }
        $this->artisan('transactions:dedupe-by-description', ['--force' => true])->assertSuccessful();
        $this->assertSame(5, Transaction::query()->count());
    }

    public function test_dry_run_does_not_delete_and_model_delete_reverses_balance(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100, 'is_active' => true]);
        foreach ([1, 2] as $index) {
            Transaction::create(['account_id' => $account->id, 'category_id' => $child->id, 'transaction_type' => 'expense', 'amount' => 10, 'description' => 'BALANCE-DUPE', 'transaction_date' => '2026-01-10', 'payment_method' => 'Bank Transfer']);
        }
        $this->assertSame('80.00', $account->refresh()->current_balance);
        $this->artisan('transactions:dedupe-by-description', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(2, Transaction::query()->count());
        $this->assertSame('80.00', $account->refresh()->current_balance);
        $this->artisan('transactions:dedupe-by-description', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame('90.00', $account->refresh()->current_balance);
    }
}
