<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionSchemaDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_table_has_retained_metadata_columns(): void
    {
        foreach (['payee_payer', 'tax', 'status', 'notes'] as $column) {
            $this->assertTrue(Schema::hasColumn('transactions', $column), "transactions.{$column} column is missing");
        }
    }

    public function test_store_persists_all_supported_transaction_fields(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();

        $response = $this->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => '125.50',
            'tax' => '12.34',
            'description' => 'Dinner with team',
            'payee_payer' => 'Corner Cafe',
            'notes' => 'Quarterly planning dinner',
            'transaction_date' => '2026-07-20',
            'transaction_time' => '19:45',
            'status' => Transaction::STATUS_PENDING,
            'payment_method' => 'UPI',
            'reference_number' => 'DIN-001',
            'tags' => 'food, work',
            'location' => 'Bengaluru',
        ]);

        $response->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();

        $transaction = Transaction::query()->where('reference_number', 'DIN-001')->firstOrFail();

        $this->assertSame('125.50', $transaction->amount);
        $this->assertSame('12.34', $transaction->tax);
        $this->assertSame('Dinner with team', $transaction->description);
        $this->assertSame('Corner Cafe', $transaction->payee_payer);
        $this->assertSame('Quarterly planning dinner', $transaction->notes);
        $this->assertSame('2026-07-20', $transaction->transaction_date->format('Y-m-d'));
        $this->assertSame('19:45', substr((string) $transaction->transaction_time, 0, 5));
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->status);
        $this->assertSame('UPI', $transaction->payment_method);
        $this->assertSame('food, work', $transaction->tags);
        $this->assertSame('Bengaluru', $transaction->location);
    }

    public function test_update_persists_all_supported_transaction_fields_with_canonical_transaction_date(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();
        $transaction = $this->transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Original',
            'transaction_date' => '2026-07-19',
        ]);

        $response = $this->put(route('transactions.update', $transaction), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => '250.75',
            'tax' => '18.50',
            'description' => 'Updated dinner',
            'payee_payer' => 'Updated Cafe',
            'notes' => 'Updated notes',
            'transaction_date' => '2026-07-21',
            'transaction_time' => '20:15',
            'status' => Transaction::STATUS_CLEARED,
            'payment_method' => 'Cash',
            'reference_number' => 'DIN-002',
            'tags' => 'food, updated',
            'location' => 'Mumbai',
        ]);

        $response->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();

        $transaction->refresh();

        $this->assertSame('250.75', $transaction->amount);
        $this->assertSame('18.50', $transaction->tax);
        $this->assertSame('Updated dinner', $transaction->description);
        $this->assertSame('Updated Cafe', $transaction->payee_payer);
        $this->assertSame('Updated notes', $transaction->notes);
        $this->assertSame('2026-07-21', $transaction->transaction_date->format('Y-m-d'));
        $this->assertSame('20:15', substr((string) $transaction->transaction_time, 0, 5));
        $this->assertSame(Transaction::STATUS_CLEARED, $transaction->status);
        $this->assertSame('Cash', $transaction->payment_method);
        $this->assertSame('DIN-002', $transaction->reference_number);
        $this->assertSame('food, updated', $transaction->tags);
        $this->assertSame('Mumbai', $transaction->location);
    }

    public function test_transfer_store_copies_supported_metadata_to_both_legs(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);

        $response = $this->post(route('transactions.store'), [
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => '500.00',
            'tax' => '1.25',
            'description' => 'Move savings',
            'payee_payer' => 'Self transfer',
            'notes' => 'Monthly sweep',
            'transaction_date' => '2026-07-22',
            'transaction_time' => '10:30',
            'status' => Transaction::STATUS_PENDING,
            'reference_number' => 'TRF-001',
            'tags' => 'transfer, savings',
            'location' => 'Online',
        ]);

        $response->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();

        $legs = Transaction::query()->orderBy('id')->get();

        $this->assertCount(2, $legs);
        foreach ($legs as $leg) {
            $this->assertSame(Transaction::TYPE_TRANSFER, $leg->transaction_type);
            $this->assertSame('500.00', $leg->amount);
            $this->assertSame('1.25', $leg->tax);
            $this->assertSame('Self transfer', $leg->payee_payer);
            $this->assertSame('Monthly sweep', $leg->notes);
            $this->assertSame(Transaction::STATUS_PENDING, $leg->status);
            $this->assertSame('TRF-001', $leg->reference_number);
            $this->assertSame('transfer, savings', $leg->tags);
            $this->assertNull($leg->payment_method);
        }

        $this->assertSame('9500.00', $source->fresh()->current_balance);
        $this->assertSame('1500.00', $destination->fresh()->current_balance);
    }

    public function test_validation_rejects_invalid_type_non_positive_amount_invalid_status_and_negative_tax(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();
        $payload = [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => '10.00',
            'transaction_date' => '2026-07-20',
            'payment_method' => 'UPI',
        ];

        $this->from(route('transactions.create'))
            ->post(route('transactions.store'), array_merge($payload, ['transaction_type' => 'refund']))
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('transaction_type');

        $this->from(route('transactions.create'))
            ->post(route('transactions.store'), array_merge($payload, ['amount' => '0']))
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('amount');

        $this->from(route('transactions.create'))
            ->post(route('transactions.store'), array_merge($payload, ['status' => 'Imported']))
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('status');

        $this->from(route('transactions.create'))
            ->post(route('transactions.store'), array_merge($payload, ['tax' => '-1.00']))
            ->assertRedirect(route('transactions.create'))
            ->assertSessionHasErrors('tax');
    }

    public function test_index_applies_and_retains_every_supported_filter(): void
    {
        $accountA = Account::factory()->create(['name' => 'Wallet A']);
        $accountB = Account::factory()->create(['name' => 'Wallet B']);
        $parent = Category::factory()->parent()->create(['name' => 'Food']);
        $childA = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Dining']);
        $childB = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Groceries']);

        $match = $this->transaction([
            'account_id' => $accountA->id,
            'category_id' => $childA->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 300,
            'description' => 'Dinner bill',
            'payee_payer' => 'Schema Cafe',
            'notes' => 'Project meetup',
            'transaction_date' => '2026-07-20',
            'payment_method' => 'UPI',
            'status' => Transaction::STATUS_PENDING,
            'tags' => 'food, friends',
        ]);
        $this->transaction([
            'account_id' => $accountB->id,
            'category_id' => $childB->id,
            'transaction_type' => Transaction::TYPE_INCOME,
            'amount' => 900,
            'description' => 'Salary credit',
            'payee_payer' => 'Employer',
            'notes' => 'Monthly income',
            'transaction_date' => '2026-07-21',
            'payment_method' => 'Bank Transfer',
            'status' => Transaction::STATUS_CLEARED,
            'tags' => 'income',
        ]);

        $this->get(route('transactions.index', [
            'search' => 'Project meetup',
            'category' => $parent->id,
            'subcategory' => $childA->id,
            'account' => $accountA->id,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'payment_method' => 'UPI',
            'status' => Transaction::STATUS_PENDING,
            'tags' => 'friends',
            'cash_flow' => 'debit',
            'sort_by' => 'amount_asc',
        ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Index', false)
                ->where('filters.search', 'Project meetup')
                ->where('filters.category', (string) $parent->id)
                ->where('filters.subcategory', (string) $childA->id)
                ->where('filters.account', (string) $accountA->id)
                ->where('filters.date_from', '2026-07-01')
                ->where('filters.date_to', '2026-07-31')
                ->where('filters.payment_method', 'UPI')
                ->where('filters.status', Transaction::STATUS_PENDING)
                ->where('filters.tags', 'friends')
                ->where('filters.cash_flow', 'debit')
                ->where('filters.sort_by', 'amount_asc')
                ->where('transactions.data.0.id', $match->id)
                ->where('transactions.total', 1)
            );
    }

    public function test_transaction_type_filter_is_retained_when_cash_flow_is_not_selected(): void
    {
        $account = Account::factory()->create();
        $category = Category::factory()->create();
        $this->transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_INCOME,
            'description' => 'Matched income',
        ]);
        $this->transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'description' => 'Excluded expense',
        ]);

        $this->get(route('transactions.index', ['transaction_type' => Transaction::TYPE_INCOME]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.transaction_type', Transaction::TYPE_INCOME)
                ->where('transactions.data.0.description', 'Matched income')
                ->where('transactions.total', 1)
            );
    }

    public function test_cash_flow_filters_apply_transfer_direction(): void
    {
        $this->seed(CategorySeeder::class);
        $source = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);
        $destination = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);

        $this->post(route('transactions.store'), [
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => '500.00',
            'transaction_date' => '2026-07-22',
        ])->assertRedirect(route('transactions.index'))->assertSessionHasNoErrors();

        $outgoing = Transaction::query()
            ->whereHas('category', fn ($query) => $query->where('code', Transaction::CATEGORY_TRANSFER_OUTGOING))
            ->firstOrFail();
        $incoming = Transaction::query()
            ->whereHas('category', fn ($query) => $query->where('code', Transaction::CATEGORY_TRANSFER_INCOMING))
            ->firstOrFail();

        $this->get(route('transactions.index', ['cash_flow' => 'debit']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.total', 1)
                ->where('transactions.data.0.id', $outgoing->id)
            );

        $this->get(route('transactions.index', ['cash_flow' => 'credit']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.total', 1)
                ->where('transactions.data.0.id', $incoming->id)
            );
    }

    public function test_export_includes_retained_metadata_and_applies_retained_filters(): void
    {
        $account = Account::factory()->create(['name' => 'Export Wallet']);
        $category = Category::factory()->create(['name' => 'Export Category']);
        $this->transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Exported row',
            'payee_payer' => 'Export Payee',
            'notes' => 'Export note match',
            'status' => Transaction::STATUS_CANCELLED,
            'tax' => '7.50',
            'tags' => 'export-tag',
            'reference_number' => 'EXP-OK',
            'location' => 'Export location',
        ]);
        $this->transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Filtered out row',
            'status' => Transaction::STATUS_CLEARED,
            'tags' => 'other-tag',
            'reference_number' => 'EXP-NO',
        ]);

        $content = $this->get(route('transactions.export', [
            'search' => 'Export Payee',
            'status' => Transaction::STATUS_CANCELLED,
            'tags' => 'export-tag',
        ]))->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($content)));
        $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);

        $this->assertSame([
            'Date', 'Account', 'Category', 'Subcategory', 'Type', 'Debit', 'Credit',
            'Description', 'Reference', 'Payment method', 'Tags', 'Payee/Payer',
            'Status', 'Tax', 'Notes', 'Location',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('Exported row', $rows[1][7]);
        $this->assertSame('EXP-OK', $rows[1][8]);
        $this->assertSame('export-tag', $rows[1][10]);
        $this->assertSame('Export Payee', $rows[1][11]);
        $this->assertSame(Transaction::STATUS_CANCELLED, $rows[1][12]);
        $this->assertSame('7.50', $rows[1][13]);
        $this->assertSame('Export note match', $rows[1][14]);
        $this->assertSame('Export location', $rows[1][15]);
    }

    private function transaction(array $attributes = []): Transaction
    {
        return Transaction::withoutEvents(fn () => Transaction::create(array_merge([
            'account_id' => Account::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => '100.00',
            'description' => 'Test transaction',
            'transaction_date' => '2026-07-20',
            'transaction_time' => '12:00',
            'status' => Transaction::STATUS_CLEARED,
            'payment_method' => 'UPI',
        ], $attributes)));
    }
}
