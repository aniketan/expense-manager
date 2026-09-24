<?php

namespace Tests\Feature;

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\TransactionRuleViolation;
use App\Actions\Transactions\UpdateTransaction;
use App\AI\Tools\ManageResourceTool;
use App\Console\Commands\McpServerCommand;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedTransactionActionsTest extends TestCase
{
    use RefreshDatabase;

    private const GROCERIES = 30;

    private const SALARY = 84;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->account = Account::factory()->create([
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // The same category/type rules through every entry point
    // ---------------------------------------------------------------------

    public function test_web_form_rejects_income_category_on_an_expense(): void
    {
        $this->post(route('transactions.store'), $this->webPayload(Transaction::TYPE_EXPENSE, self::SALARY))
            ->assertSessionHasErrors(['category_id' => 'Expense transactions cannot use an income or account-transfer category.']);

        $this->assertNothingSaved();
    }

    public function test_web_form_rejects_expense_category_on_income(): void
    {
        $this->post(route('transactions.store'), $this->webPayload(Transaction::TYPE_INCOME, self::GROCERIES))
            ->assertSessionHasErrors(['category_id' => 'Income transactions need a category under Income.']);

        $this->assertNothingSaved();
    }

    public function test_web_form_accepts_matching_categories(): void
    {
        $this->post(route('transactions.store'), $this->webPayload(Transaction::TYPE_INCOME, self::SALARY))
            ->assertSessionHasNoErrors();
        $this->post(route('transactions.store'), $this->webPayload(Transaction::TYPE_EXPENSE, self::GROCERIES))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Transaction::count());
        $this->assertSame('1000.00', $this->account->fresh()->current_balance); // +100 -100
    }

    public function test_web_update_rejects_switching_type_without_a_matching_category(): void
    {
        $expense = $this->expense(self::GROCERIES);

        $this->put(route('transactions.update', $expense), $this->webPayload(Transaction::TYPE_INCOME, self::GROCERIES))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(Transaction::TYPE_EXPENSE, $expense->fresh()->transaction_type);
        $this->assertSame('900.00', $this->account->fresh()->current_balance);
    }

    public function test_legacy_row_with_mismatched_category_stays_editable_when_category_is_unchanged(): void
    {
        // Rows created before these rules existed may already pair an expense with an income category.
        $legacy = $this->expense(self::SALARY);

        $this->put(route('transactions.update', $legacy), array_merge(
            $this->webPayload(Transaction::TYPE_EXPENSE, self::SALARY),
            ['amount' => 40],
        ))->assertSessionHasNoErrors();

        $this->assertSame('40.00', $legacy->fresh()->amount);
        $this->assertSame('960.00', $this->account->fresh()->current_balance);
    }

    public function test_statement_import_rejects_expense_category_on_income(): void
    {
        $this->from(route('statements.upload'))->post(route('statements.import'), [
            'rows' => [[
                'date' => '2026-09-01',
                'description' => 'Salary credit',
                'amount' => 100,
                'type' => 'income',
                'account_id' => $this->account->id,
                'category_id' => self::GROCERIES,
                'reference' => null,
                'review_row_index' => 0,
            ]],
        ])->assertSessionHasErrors('rows');

        $this->assertNothingSaved();
    }

    public function test_ai_tool_rejects_type_switch_that_leaves_a_mismatched_category(): void
    {
        $expense = $this->expense(self::GROCERIES);

        $result = json_decode((new ManageResourceTool)->execute('update', 'transaction', $expense->id, transaction_type: 'income'), true);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Income transactions need a category under Income.', $result['error']);
        $this->assertSame(Transaction::TYPE_EXPENSE, $expense->fresh()->transaction_type);
    }

    public function test_ai_tool_can_switch_type_together_with_a_matching_category(): void
    {
        $expense = $this->expense(self::GROCERIES);

        $result = json_decode((new ManageResourceTool)->execute(
            'update', 'transaction', $expense->id, category_name: 'Salary', transaction_type: 'income'
        ), true);

        $this->assertTrue($result['success']);
        $this->assertSame(self::SALARY, $expense->fresh()->category_id);
        $this->assertSame('1100.00', $this->account->fresh()->current_balance);
    }

    public function test_mcp_create_goes_through_the_shared_rules(): void
    {
        $response = app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'create_transaction', 'arguments' => [
                'amount' => 500, 'description' => 'Bonus', 'type' => 'income', 'category' => 'Salary',
            ]],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertSame(self::SALARY, Transaction::sole()->category_id);
        $this->assertSame('1500.00', $this->account->fresh()->current_balance);
    }

    // ---------------------------------------------------------------------
    // Transfers through the shared actions
    // ---------------------------------------------------------------------

    public function test_statement_enrichment_moves_a_transfer_date_on_both_legs(): void
    {
        $destination = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
        [$outgoing, $incoming] = app(AccountTransferService::class)->create([
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ]);

        $this->from(route('statements.upload'))->post(route('statements.enrich'), [
            'updates' => [[
                'transaction_id' => $outgoing->id,
                'description' => 'NEFT to savings',
                'transaction_date' => '2026-09-03',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-03', $outgoing->fresh()->transaction_date->toDateString());
        $this->assertSame('2026-09-03', $incoming->fresh()->transaction_date->toDateString());
        $this->assertSame('NEFT to savings', $incoming->fresh()->description);
        $this->assertSame('800.00', $this->account->fresh()->current_balance);
        $this->assertSame('200.00', $destination->fresh()->current_balance);
    }

    public function test_actions_guard_transfer_invariants(): void
    {
        $create = app(CreateTransaction::class);

        try {
            $create->handle([
                'account_id' => $this->account->id,
                'transfer_to_account_id' => $this->account->id,
                'transaction_type' => Transaction::TYPE_TRANSFER,
                'amount' => 50,
                'transaction_date' => '2026-09-01',
            ]);
            $this->fail('Transfer to the same account was accepted.');
        } catch (TransactionRuleViolation $violation) {
            $this->assertArrayHasKey('transfer_to_account_id', $violation->errors());
        }

        $expense = $this->expense(self::GROCERIES);
        $this->expectException(TransactionRuleViolation::class);
        app(UpdateTransaction::class)->handle($expense, ['transaction_type' => Transaction::TYPE_TRANSFER]);
    }

    public function test_delete_action_reports_rows_removed(): void
    {
        $destination = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
        [$outgoing] = app(AccountTransferService::class)->create([
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $destination->id,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ]);
        $expense = $this->expense(self::GROCERIES);

        $this->assertSame(2, app(DeleteTransaction::class)->handle($outgoing));
        $this->assertSame(1, app(DeleteTransaction::class)->handle($expense));
        $this->assertNothingSaved();
        $this->assertSame('0.00', $destination->fresh()->current_balance);
    }

    public function test_income_is_unrestricted_when_no_income_tree_exists(): void
    {
        Transaction::query()->delete();
        Category::query()->whereRaw('LOWER(code) = ?', ['income'])->update(['code' => 'earnings', 'name' => 'Earnings']);

        app(CreateTransaction::class)->handle([
            'account_id' => $this->account->id,
            'category_id' => self::GROCERIES,
            'transaction_type' => Transaction::TYPE_INCOME,
            'amount' => 10,
            'transaction_date' => '2026-09-01',
        ]);

        $this->assertSame(1, Transaction::count());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function webPayload(string $type, int $categoryId): array
    {
        return [
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'transaction_type' => $type,
            'amount' => 100,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ];
    }

    private function expense(int $categoryId): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ]);
    }

    private function assertNothingSaved(): void
    {
        $this->assertSame(0, Transaction::count());
    }
}
