<?php

namespace Tests\Feature;

use App\Console\Commands\SyncExpenseData;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\ExpenseSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncExpenseDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeSyncCommand(): void
    {
        $this->app->bind(SyncExpenseData::class, fn () => new class extends SyncExpenseData
        {
            protected function makeSyncService(?string $dbPath): ExpenseSyncService
            {
                return new class extends ExpenseSyncService
                {
                    public function __construct() {}

                    public function sync(bool $dryRun = false): array
                    {
                        return ['categories_synced' => 0, 'accounts_synced' => 0, 'transactions_synced' => 0, 'errors' => 0];
                    }
                };
            }
        });
    }

    public function test_fresh_dry_run_previews_without_deleting_ext_transactions(): void
    {
        $this->bindFakeSyncCommand();
        [$account] = $this->makeAccountWithExtTransaction('EXT_1', 10);
        $this->assertSame('90.00', $account->refresh()->current_balance);
        $this->artisan('expense:sync', ['--fresh' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Transaction::query()->where('reference_number', 'EXT_1')->count());
        $this->assertSame('90.00', $account->refresh()->current_balance);
    }

    public function test_fresh_force_deletes_ext_transactions_with_model_balance_events(): void
    {
        $this->bindFakeSyncCommand();
        [$account, $child] = $this->makeAccountWithExtTransaction('EXT_2', 10);
        Transaction::create(['account_id' => $account->id, 'category_id' => $child->id, 'transaction_type' => 'expense', 'amount' => 7, 'description' => 'Manual', 'transaction_date' => '2026-01-11', 'payment_method' => 'Bank Transfer', 'reference_number' => 'MANUAL']);
        $this->assertSame('83.00', $account->refresh()->current_balance);
        $this->artisan('expense:sync', ['--fresh' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(0, Transaction::query()->where('reference_number', 'EXT_2')->count());
        $this->assertSame(1, Transaction::query()->where('reference_number', 'MANUAL')->count());
        $this->assertSame('93.00', $account->refresh()->current_balance);
    }

    private function makeAccountWithExtTransaction(string $referenceNumber, int $amount): array
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100, 'is_active' => true]);
        Transaction::create(['account_id' => $account->id, 'category_id' => $child->id, 'transaction_type' => 'expense', 'amount' => $amount, 'description' => 'External', 'transaction_date' => '2026-01-10', 'payment_method' => 'Bank Transfer', 'reference_number' => $referenceNumber]);

        return [$account, $child];
    }
}
