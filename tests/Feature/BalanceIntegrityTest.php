<?php

namespace Tests\Feature;

use App\AI\Tools\CreateTransactionTool;
use App\AI\Tools\ManageResourceTool;
use App\Console\Commands\McpServerCommand;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class BalanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const GROCERIES = 30;

    private const SALARY = 84;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    // ---------------------------------------------------------------------
    // Account deletion
    // ---------------------------------------------------------------------

    public function test_account_with_transactions_cannot_be_deleted_and_transfer_counterpart_is_untouched(): void
    {
        $source = $this->account(10000);
        $destination = $this->account(1000);
        $this->transfer($source, $destination, 500);

        $this->delete(route('accounts.destroy', $source))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', 'Cannot delete account. Reassign or delete its transactions first, or deactivate the account instead.');

        $this->assertDatabaseHas('accounts', ['id' => $source->id]);
        $this->assertSame(2, Transaction::count());
        $this->assertSame('9500.00', $source->fresh()->current_balance);
        $this->assertSame('1500.00', $destination->fresh()->current_balance);
    }

    public function test_database_rejects_account_deletion_when_transactions_exist(): void
    {
        $account = $this->account(1000);
        $this->expense($account, 100);

        try {
            DB::table('accounts')->where('id', $account->id)->delete();
            $this->fail('Database allowed deletion of an account referenced by transactions.');
        } catch (QueryException) {
            // The foreign key is the integrity backstop behind the controller check.
        }

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertSame(1, Transaction::count());
    }

    public function test_account_without_transactions_can_be_deleted(): void
    {
        $account = $this->account(1000);

        $this->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('success', 'Account deleted successfully.');

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    // ---------------------------------------------------------------------
    // Transfer categories are reserved for transfer legs
    // ---------------------------------------------------------------------

    public function test_web_form_rejects_regular_transaction_with_transfer_category(): void
    {
        $account = $this->account(1000);

        $this->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $this->transferCategoryId(Transaction::CATEGORY_TRANSFER_INCOMING),
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $account->fresh()->current_balance);
    }

    public function test_web_form_update_rejects_switching_to_transfer_category(): void
    {
        $account = $this->account(1000);
        $transaction = $this->expense($account, 100);

        $this->put(route('transactions.update', $transaction), [
            'account_id' => $account->id,
            'category_id' => $this->transferCategoryId(Transaction::CATEGORY_TRANSFER_OUTGOING),
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(self::GROCERIES, $transaction->fresh()->category_id);
    }

    public function test_model_refuses_regular_transaction_with_transfer_category(): void
    {
        $account = $this->account(1000);

        $this->expectException(InvalidArgumentException::class);

        try {
            Transaction::create([
                'account_id' => $account->id,
                'category_id' => $this->transferCategoryId(Transaction::CATEGORY_TRANSFER_INCOMING),
                'transaction_type' => Transaction::TYPE_EXPENSE,
                'amount' => 100,
                'transaction_date' => '2026-09-01',
            ]);
        } finally {
            $this->assertSame('1000.00', $account->fresh()->current_balance);
        }
    }

    public function test_statement_import_rejects_transfer_category(): void
    {
        $account = $this->account(1000);

        $this->from(route('statements.upload'))->post(route('statements.import'), [
            'rows' => [[
                'date' => '2026-09-01',
                'description' => 'NEFT to savings',
                'amount' => 100,
                'type' => 'expense',
                'account_id' => $account->id,
                'category_id' => $this->transferCategoryId(Transaction::CATEGORY_TRANSFER_INCOMING),
                'reference' => null,
                'review_row_index' => 0,
            ]],
        ])->assertSessionHasErrors('rows');

        $this->assertSame(0, Transaction::count());
    }

    // ---------------------------------------------------------------------
    // AI chat tools
    // ---------------------------------------------------------------------

    public function test_ai_delete_of_transfer_removes_both_legs_and_restores_both_balances(): void
    {
        $source = $this->account(10000);
        $destination = $this->account(1000);
        [, $incoming] = $this->transfer($source, $destination, 500);

        $result = json_decode((new ManageResourceTool)->execute('delete', 'transaction', $incoming->id, 'yes'), true);

        $this->assertTrue($result['success']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('10000.00', $source->fresh()->current_balance);
        $this->assertSame('1000.00', $destination->fresh()->current_balance);
    }

    public function test_ai_update_of_transfer_amount_updates_both_legs(): void
    {
        $source = $this->account(10000);
        $destination = $this->account(1000);
        [$outgoing] = $this->transfer($source, $destination, 500);

        $result = json_decode((new ManageResourceTool)->execute('update', 'transaction', $outgoing->id, amount: 750), true);

        $this->assertTrue($result['success']);
        $this->assertSame(['750.00', '750.00'], Transaction::orderBy('id')->pluck('amount')->all());
        $this->assertSame('9250.00', $source->fresh()->current_balance);
        $this->assertSame('1750.00', $destination->fresh()->current_balance);
    }

    public function test_ai_update_cannot_change_transfer_type_or_category(): void
    {
        $source = $this->account(10000);
        $destination = $this->account(1000);
        [$outgoing] = $this->transfer($source, $destination, 500);

        $result = json_decode((new ManageResourceTool)->execute('update', 'transaction', $outgoing->id, transaction_type: 'expense'), true);

        $this->assertFalse($result['success']);
        $this->assertSame(Transaction::TYPE_TRANSFER, $outgoing->fresh()->transaction_type);
        $this->assertSame('9500.00', $source->fresh()->current_balance);
    }

    public function test_ai_update_rejects_category_of_the_wrong_type(): void
    {
        $account = $this->account(1000);
        $transaction = $this->expense($account, 100);

        $result = json_decode((new ManageResourceTool)->execute('update', 'transaction', $transaction->id, category_name: 'Salary'), true);

        $this->assertFalse($result['success']);
        $this->assertSame(self::GROCERIES, $transaction->fresh()->category_id);
    }

    public function test_ai_create_rejects_transfer_type(): void
    {
        $account = $this->account(1000);

        $result = json_decode((new CreateTransactionTool)->execute(100, 'Move money', 'transfer'), true);

        $this->assertFalse($result['success']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $account->fresh()->current_balance);
    }

    public function test_ai_create_never_resolves_a_transfer_category(): void
    {
        $account = $this->account(1000);

        $result = json_decode((new CreateTransactionTool)->execute(100, 'Bank transfer fee', 'expense', 'Transfer Incoming'), true);

        $this->assertTrue($result['success']);
        $transaction = Transaction::sole();
        $this->assertFalse($transaction->category->isTransferCategory());
        $this->assertSame('900.00', $account->fresh()->current_balance);
    }

    public function test_ai_create_income_uses_an_income_category(): void
    {
        $this->account(1000);

        $result = json_decode((new CreateTransactionTool)->execute(5000, 'September salary', 'income', 'Salary'), true);

        $this->assertTrue($result['success']);
        $this->assertSame(self::SALARY, Transaction::sole()->category_id);
    }

    // ---------------------------------------------------------------------
    // MCP server
    // ---------------------------------------------------------------------

    public function test_mcp_notifications_get_no_response(): void
    {
        $server = app(McpServerCommand::class);

        $this->assertNull($server->respond(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        $this->assertSame(1, $server->respond(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])['id']);
    }

    public function test_mcp_create_transaction_rejects_invalid_type(): void
    {
        $this->account(1000);

        $response = app(McpServerCommand::class)->respond($this->mcpCreate(['amount' => 100, 'description' => 'x', 'type' => 'transfer']));

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(0, Transaction::count());
    }

    public function test_mcp_create_transaction_uses_a_category_matching_the_type(): void
    {
        $account = $this->account(1000);

        $response = app(McpServerCommand::class)->respond($this->mcpCreate([
            'amount' => 40, 'description' => 'Veggies', 'type' => 'expense', 'category' => 'Groceries',
        ]));

        $this->assertArrayHasKey('result', $response);
        $this->assertSame(self::GROCERIES, Transaction::sole()->category_id);
        $this->assertSame('960.00', $account->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // Legacy transfers without a group id
    // ---------------------------------------------------------------------

    public function test_legacy_unlinked_transfer_can_be_deleted_and_restores_balance(): void
    {
        $account = $this->account(1000);
        $legacy = $this->legacyTransfer($account, 200);
        $this->assertSame('800.00', $account->fresh()->current_balance);

        $this->delete(route('transactions.destroy', $legacy))
            ->assertRedirect(route('transactions.index'));

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $account->fresh()->current_balance);
    }

    public function test_bulk_delete_removes_every_selected_legacy_transfer(): void
    {
        $account = $this->account(1000);
        $first = $this->legacyTransfer($account, 200);
        $second = $this->legacyTransfer($account, 300);

        $this->post(route('transactions.bulk-destroy'), ['ids' => [$first->id, $second->id]])
            ->assertSessionHas('success', '2 transaction(s) deleted successfully.');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $account->fresh()->current_balance);
    }

    public function test_editing_legacy_unlinked_transfer_redirects_with_an_explanation(): void
    {
        $legacy = $this->legacyTransfer($this->account(1000), 200);

        $this->get(route('transactions.edit', $legacy))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------------
    // Dedupe command
    // ---------------------------------------------------------------------

    public function test_dedupe_command_never_deletes_transfer_legs(): void
    {
        $source = $this->account(10000);
        $destination = $this->account(1000);
        $this->transfer($source, $destination, 500, 'Monthly savings');
        $this->transfer($source, $destination, 500, 'Monthly savings');

        $this->artisan('transactions:dedupe-by-description', ['--force' => true])
            ->expectsOutput('No duplicate transaction groups found.')
            ->assertSuccessful();

        $this->assertSame(4, Transaction::count());
        $this->assertSame('9000.00', $source->fresh()->current_balance);
        $this->assertSame('2000.00', $destination->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function account(float $balance): Account
    {
        return Account::factory()->create([
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function expense(Account $account, float $amount): Transaction
    {
        return Transaction::create([
            'account_id' => $account->id,
            'category_id' => self::GROCERIES,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => $amount,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ]);
    }

    /**
     * @return array{0: Transaction, 1: Transaction}
     */
    private function transfer(Account $source, Account $destination, float $amount, ?string $description = null): array
    {
        return app(AccountTransferService::class)->create([
            'account_id' => $source->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => '2026-09-01',
        ]);
    }

    private function legacyTransfer(Account $account, float $amount): Transaction
    {
        return Transaction::create([
            'account_id' => $account->id,
            'category_id' => $this->transferCategoryId(Transaction::CATEGORY_TRANSFER_OUTGOING),
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => $amount,
            'description' => 'Synced transfer',
            'transaction_date' => '2026-09-01',
        ]);
    }

    private function transferCategoryId(string $code): int
    {
        return Category::where('code', $code)->value('id');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function mcpCreate(array $arguments): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => ['name' => 'create_transaction', 'arguments' => $arguments],
        ];
    }
}
