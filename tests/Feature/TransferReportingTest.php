<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransferReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_does_not_change_income_expense_or_net_totals(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);

        Transaction::create([
            'account_id' => $source->id,
            'category_id' => Category::where('code', 'salary')->value('id'),
            'transaction_type' => Transaction::TYPE_INCOME,
            'amount' => 2000,
            'transaction_date' => '2026-07-18',
            'payment_method' => 'Bank Transfer',
        ]);
        Transaction::create([
            'account_id' => $source->id,
            'category_id' => Category::where('code', 'groceries')->value('id'),
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 300,
            'transaction_date' => '2026-07-18',
            'payment_method' => 'UPI',
        ]);
        app(AccountTransferService::class)->create([
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 500,
            'transaction_date' => '2026-07-18',
        ]);

        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totals.total_income', 2000)
                ->where('totals.total_expenses', 300)
                ->where('totals.net_balance', 1700)
            );

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.totalIncome', 2000)
                ->where('stats.totalExpenses', 300)
                ->where('stats.netBalance', 1700)
            );

        $this->getJson(route('api.stats.dashboard'))
            ->assertOk()
            ->assertJsonPath('overall.total_income', 2000)
            ->assertJsonPath('overall.total_expense', 300)
            ->assertJsonPath('overall.net_balance', 1700);
    }

    public function test_csv_maps_incoming_transfer_to_credit_and_outgoing_to_debit(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create();
        $destination = Account::factory()->create();
        app(AccountTransferService::class)->create([
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 500,
            'transaction_date' => '2026-07-18',
        ]);

        $content = $this->get(route('transactions.export'))->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($content)));

        $this->assertSame('500.00', $rows[1][5]);
        $this->assertSame('', $rows[1][6]);
        $this->assertSame('', $rows[2][5]);
        $this->assertSame('500.00', $rows[2][6]);
    }
}
