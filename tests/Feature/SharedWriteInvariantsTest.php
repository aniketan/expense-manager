<?php

namespace Tests\Feature;

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\TransactionRuleViolation;
use App\Actions\Transactions\UpdateTransaction;
use App\AI\Tools\CreateTransactionTool;
use App\AI\Tools\ManageResourceTool;
use App\Console\Commands\McpServerCommand;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #66: the amount guard, the transfer pairing rules, and the delete
 * confirmation gate must hold through every write entry point (web, statement
 * import/enrichment, AI tools, MCP, shared actions alike). Each failure path
 * asserts the guard fired AND no money moved (re-queried rows + balances).
 */
class SharedWriteInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const GROCERIES = 30;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->account = Account::factory()->create([
            'type' => Account::TYPE_SAVINGS,
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Amount guard: zero/negative amounts move no money, from any surface
    // ---------------------------------------------------------------------

    public function test_web_store_rejects_zero_amount_and_moves_no_money(): void
    {
        $this->post(route('transactions.store'), $this->webPayload(['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_web_store_creates_expense_and_updates_balance(): void
    {
        $this->post(route('transactions.store'), $this->webPayload(['amount' => 100]))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('success', 'Transaction created successfully.');

        $this->assertSame(1, Transaction::count());
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    public function test_web_update_rejects_zero_amount_and_keeps_old_balance(): void
    {
        $expense = $this->expense(100);

        $this->put(route('transactions.update', $expense), $this->webPayload(['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->assertSame('100.00', $expense->fresh()->amount);
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    public function test_direct_create_action_rejects_zero_amount(): void
    {
        try {
            app(CreateTransaction::class)->handle([
                'account_id' => $this->account->id,
                'category_id' => self::GROCERIES,
                'transaction_type' => Transaction::TYPE_EXPENSE,
                'amount' => 0,
                'transaction_date' => '2026-09-01',
            ]);
            $this->fail('Zero-amount transaction was accepted by the shared action.');
        } catch (TransactionRuleViolation $violation) {
            $this->assertArrayHasKey('amount', $violation->errors());
        }

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_direct_update_action_rejects_zero_amount(): void
    {
        $expense = $this->expense(100);

        try {
            app(UpdateTransaction::class)->handle($expense, ['amount' => -5]);
            $this->fail('Negative-amount update was accepted by the shared action.');
        } catch (TransactionRuleViolation $violation) {
            $this->assertArrayHasKey('amount', $violation->errors());
        }

        $this->assertSame('100.00', $expense->fresh()->amount);
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    public function test_statement_import_rejects_zero_amount_and_imports_nothing(): void
    {
        $this->from(route('statements.upload'))->post(route('statements.import'), [
            'rows' => [[
                'date' => '2026-09-01',
                'description' => 'Zero row',
                'amount' => 0,
                'type' => 'expense',
                'account_id' => $this->account->id,
                'category_id' => self::GROCERIES,
                'reference' => null,
                'review_row_index' => 0,
            ]],
        ])->assertSessionHasErrors('rows.0.amount');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_statement_enrich_rejects_unknown_transaction_and_changes_nothing(): void
    {
        $expense = $this->expense(100);

        $this->from(route('statements.upload'))->post(route('statements.enrich'), [
            'updates' => [[
                'transaction_id' => 999999,
                'description' => 'Ghost row',
            ]],
        ])->assertSessionHasErrors('updates.0.transaction_id');

        $this->assertSame(1, Transaction::count());
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
        $this->assertNotSame('Ghost row', $expense->fresh()->description);
    }

    public function test_mcp_create_rejects_zero_amount_and_moves_no_money(): void
    {
        $response = app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'create_transaction', 'arguments' => [
                'amount' => 0, 'description' => 'Nothing', 'type' => 'expense', 'category' => 'Groceries',
            ]],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_mcp_create_records_expense_and_updates_balance(): void
    {
        $response = app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'create_transaction', 'arguments' => [
                'amount' => 40, 'description' => 'Veggies', 'type' => 'expense', 'category' => 'Groceries',
            ]],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertSame(self::GROCERIES, Transaction::sole()->category_id);
        $this->assertSame('960.00', $this->account->fresh()->current_balance);
    }

    public function test_ai_create_tool_rejects_zero_amount_and_moves_no_money(): void
    {
        $result = json_decode((new CreateTransactionTool)->execute(0, 'Nothing', 'expense', 'Groceries'), true);

        $this->assertFalse($result['success']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_ai_create_tool_records_expense_and_updates_balance(): void
    {
        $result = json_decode((new CreateTransactionTool)->execute(100, 'Weekly shop', 'expense', 'Groceries'), true);

        $this->assertTrue($result['success']);
        $this->assertSame(self::GROCERIES, Transaction::sole()->category_id);
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // Transfer pairing through the web and bulk entry points
    // ---------------------------------------------------------------------

    public function test_web_transfer_to_same_account_fails_and_moves_no_money(): void
    {
        $this->post(route('transactions.store'), [
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $this->account->id,
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ])->assertSessionHasErrors('transfer_to_account_id');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_web_transfer_creates_both_legs_and_moves_balances(): void
    {
        $destination = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

        $this->post(route('transactions.store'), [
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $destination->id,
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ])->assertRedirect(route('transactions.index'))
            ->assertSessionHas('success', 'Account transfer completed successfully.');

        $this->assertSame(2, Transaction::count());
        $this->assertSame('800.00', $this->account->fresh()->current_balance);
        $this->assertSame('200.00', $destination->fresh()->current_balance);
    }

    public function test_web_destroy_of_expense_restores_balance(): void
    {
        $expense = $this->expense(100);

        $this->delete(route('transactions.destroy', $expense))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('success', 'Transaction deleted successfully.');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    public function test_web_bulk_destroy_of_transfer_removes_both_legs_once(): void
    {
        $destination = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
        [$outgoing, $incoming] = app(AccountTransferService::class)->create([
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ]);

        // Selecting both legs still deletes the pair exactly once.
        $this->post(route('transactions.bulk-destroy'), ['ids' => [$outgoing->id, $incoming->id]])
            ->assertSessionHas('success', '1 transaction(s) deleted successfully.');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
        $this->assertSame('0.00', $destination->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // AI delete confirmation gate
    // ---------------------------------------------------------------------

    public function test_ai_delete_without_confirmation_changes_nothing(): void
    {
        $expense = $this->expense(100);

        $result = json_decode((new ManageResourceTool)->execute('delete', 'transaction', $expense->id), true);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['pending_confirmation'] ?? false);
        $this->assertSame(1, Transaction::count());
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    public function test_ai_delete_with_confirmation_restores_balance(): void
    {
        $expense = $this->expense(100);

        $result = json_decode((new ManageResourceTool)->execute('delete', 'transaction', $expense->id, 'yes'), true);

        $this->assertTrue($result['success']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function webPayload(array $overrides = []): array
    {
        return array_merge([
            'account_id' => $this->account->id,
            'category_id' => self::GROCERIES,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ], $overrides);
    }

    private function expense(float $amount): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'category_id' => self::GROCERIES,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => $amount,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ]);
    }
}
