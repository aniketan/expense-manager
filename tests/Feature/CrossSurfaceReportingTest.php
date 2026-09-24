<?php

namespace Tests\Feature;

use App\AI\Tools\GetSpendingInsightsTool;
use App\AI\Tools\QueryExpensesTool;
use App\AI\Tools\SearchTransactionsTool;
use App\Console\Commands\McpServerCommand;
use App\Models\Account;
use App\Models\Transaction;
use App\Reporting\FinancialSummary;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The same data and period must produce the same totals on every surface (#63).
 * "Now" is March 31: the day month arithmetic used to overflow back into March.
 */
class CrossSurfaceReportingTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = 5;

    private const GROCERIES = 30;

    private const RESTAURANT = 32;

    private const SALARY = 84;

    // March: income 2000, expenses 300 + 150 = 450, net 1550, plus a 500 transfer.
    private const MARCH = ['income' => 2000.0, 'expense' => 450.0, 'net' => 1550.0];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-31 12:00:00');
        $this->seed(CategorySeeder::class);

        $main = $this->account();
        $savings = $this->account();

        $this->row($main, Transaction::TYPE_EXPENSE, self::GROCERIES, 999, '2026-02-10', 'February groceries', 'UPI');
        $this->row($main, Transaction::TYPE_INCOME, self::SALARY, 2000, '2026-03-01', 'Salary', 'Bank Transfer');
        $this->row($main, Transaction::TYPE_EXPENSE, self::GROCERIES, 300, '2026-03-15', 'Veg market', 'Cash');
        $this->row($main, Transaction::TYPE_EXPENSE, self::RESTAURANT, 150, '2026-03-31', 'Dinner', 'Credit Card');
        app(AccountTransferService::class)->create([
            'account_id' => $main->id,
            'transfer_to_account_id' => $savings->id,
            'amount' => 500,
            'transaction_date' => '2026-03-20',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_this_month_totals_match_on_every_surface(): void
    {
        // Web transaction list
        $this->get(route('transactions.index', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totals.total_income', fn ($v) => (float) $v === self::MARCH['income'])
                ->where('totals.total_expenses', fn ($v) => (float) $v === self::MARCH['expense'])
                ->where('totals.net_balance', fn ($v) => (float) $v === self::MARCH['net']));

        // Analytics dashboard
        $this->get(route('dashboard.analytics'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('chartData.monthlyComparison.0.name', 'Mar 2026')
                ->where('chartData.monthlyComparison.0.income', fn ($v) => (float) $v === self::MARCH['income'])
                ->where('chartData.monthlyComparison.0.expense', fn ($v) => (float) $v === self::MARCH['expense'])
                ->where('chartData.transactionTypeDistribution.1.value', fn ($v) => (float) $v === self::MARCH['expense']));

        // AI: query_expenses, search_transactions, get_spending_insights
        $query = $this->decoded((new QueryExpensesTool)->execute('this_month', 'both'));
        $this->assertSame(['2,000.00', '450.00', '1,550.00'], [$query['income_total'], $query['expense_total'], $query['net']]);
        $this->assertSame(3, $query['count'], 'Transfers must not be counted as income or expense.');

        $search = $this->decoded((new SearchTransactionsTool)->execute('both', from_date: '2026-03-01', to_date: '2026-03-31'));
        $this->assertSame(['2,000.00', '450.00', '1,550.00'], [$search['income_total'], $search['expense_total'], $search['net']]);

        $insights = $this->decoded((new GetSpendingInsightsTool)->execute('spending_summary', 'this_month'));
        $this->assertSame([self::MARCH['income'], self::MARCH['expense'], self::MARCH['net']], [
            (float) $insights['income'], (float) $insights['expense'], (float) $insights['net'],
        ]);

        // MCP spending summary (expenses by category)
        $mcp = json_decode(app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'spending_summary', 'arguments' => ['period' => 'this_month']],
        ])['result']['content'][0]['text'], true);
        $this->assertSame(self::MARCH['expense'], (float) array_sum(array_column($mcp, 'total')));
    }

    public function test_last_month_on_the_31st_means_february(): void
    {
        $query = $this->decoded((new QueryExpensesTool)->execute('last_month', 'expense'));
        $this->assertSame('999.00', $query['total']);

        $mcp = json_decode(app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'spending_summary', 'arguments' => ['period' => 'last_month']],
        ])['result']['content'][0]['text'], true);
        $this->assertSame(999.0, (float) array_sum(array_column($mcp, 'total')));

        $this->get(route('dashboard.analytics'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('chartData.monthlyComparison.1.name', 'Feb 2026')
                ->where('chartData.monthlyComparison.1.expense', fn ($v) => (float) $v === 999.0));
    }

    public function test_monthly_trend_lists_twelve_distinct_months_ending_this_month(): void
    {
        $trend = app(FinancialSummary::class)->monthlyTrend(12);
        $months = array_column($trend, 'month');

        $this->assertCount(12, array_unique($months));
        $this->assertSame(['Feb 2026', 'Mar 2026'], array_slice($months, -2));
        $this->assertSame(999.0, $trend[10]['expense']);
        $this->assertSame(self::MARCH['net'], $trend[11]['balance']);
    }

    public function test_parent_category_filter_includes_its_subcategories_on_every_surface(): void
    {
        $this->get(route('transactions.index', ['category' => self::FOOD, 'date_from' => '2026-03-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.total', 2)
                ->where('totals.total_expenses', fn ($v) => (float) $v === 450.0));

        $search = $this->decoded((new SearchTransactionsTool)->execute('expense', category_ids: (string) self::FOOD, from_date: '2026-03-01'));
        $this->assertSame(2, $search['count']);
        $this->assertSame('450.00', $search['total']);
    }

    public function test_ai_payment_method_filter_matches_stored_values(): void
    {
        $search = $this->decoded((new SearchTransactionsTool)->execute('expense', payment_method: 'Cash'));

        $this->assertSame(1, $search['count']);
        $this->assertSame('Veg market', $search['transactions'][0]['description']);
    }

    private function account(): Account
    {
        return Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    }

    private function row(Account $account, string $type, int $categoryId, float $amount, string $date, string $description, string $method): void
    {
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $categoryId,
            'transaction_type' => $type,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date,
            'payment_method' => $method,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decoded(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $this->assertTrue($decoded['success'] ?? false, $payload);

        return $decoded;
    }
}
