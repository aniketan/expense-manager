<?php

namespace Tests\Feature;

use App\AI\Tools\GetAccountBalancesTool;
use App\AI\Tools\GetSpendingInsightsTool;
use App\AI\Tools\QueryExpensesTool;
use App\AI\Tools\SearchTransactionsTool;
use App\Console\Commands\McpServerCommand;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;
use App\Reporting\FinancialSummary;
use App\Reporting\TransactionFilters;
use App\Services\AccountTransferService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Issue #66: the same underlying data must produce identical totals on every
 * surface that reports them (dashboard, transactions listing, CSV export,
 * budgets, analytics, AI tools, MCP). "Now" is March 31, the day month
 * arithmetic used to overflow back into March.
 */
class CrossSurfaceTotalsTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = 5;

    private const GROCERIES = 30;

    private const RESTAURANT = 32;

    private const SALARY = 84;

    // March: income 2000, expenses 300 + 150 = 450, net 1550, plus a 500 transfer.
    private const MARCH = ['income' => 2000.0, 'expense' => 450.0, 'net' => 1550.0];

    private Account $main;

    private Account $savings;

    private Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-31 12:00:00');
        $this->seed(CategorySeeder::class);

        $this->main = $this->account();
        $this->savings = $this->account();

        $this->row($this->main, Transaction::TYPE_EXPENSE, self::GROCERIES, 999, '2026-02-10', 'February groceries', 'UPI');
        $this->row($this->main, Transaction::TYPE_INCOME, self::SALARY, 2000, '2026-03-01', 'Salary', 'Bank Transfer');
        $this->row($this->main, Transaction::TYPE_EXPENSE, self::GROCERIES, 300, '2026-03-15', 'Veg market', 'Cash');
        $this->row($this->main, Transaction::TYPE_EXPENSE, self::RESTAURANT, 150, '2026-03-31', 'Dinner', 'Credit Card');
        app(AccountTransferService::class)->create([
            'account_id' => $this->main->id,
            'transfer_to_account_id' => $this->savings->id,
            'amount' => 500,
            'transaction_date' => '2026-03-20',
        ]);

        $this->budget = Budget::create([
            'category_id' => self::FOOD,
            'name' => 'March food',
            'amount' => 5000,
            'period_type' => Budget::PERIOD_CUSTOM,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_march_totals_agree_on_every_surface(): void
    {
        // Web: home dashboard stats (all time) and the March-filtered listing.
        $home = $this->pageProps($this->get(route('home'))->assertOk())['stats'];
        $this->assertSame(2000.0, (float) $home['totalIncome']);
        $this->assertSame(1449.0, (float) $home['totalExpenses']);
        $this->assertSame(551.0, (float) $home['netBalance']);

        $listing = $this->pageProps($this->get(route('transactions.index', [
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
        ]))->assertOk());
        // The listing has no type filter, so it shows the 3 income/expense rows
        // plus the 2 transfer legs; the income/expense/net totals still match.
        $this->assertSame(5, $listing['transactions']['total']);
        $this->assertSame(self::MARCH['income'], (float) $listing['totals']['total_income']);
        $this->assertSame(self::MARCH['expense'], (float) $listing['totals']['total_expenses']);
        $this->assertSame(self::MARCH['net'], (float) $listing['totals']['net_balance']);

        // Shared summary with the same explicit range agrees with the listing.
        $summary = app(FinancialSummary::class)->totals(new TransactionFilters(
            dateFrom: '2026-03-01', dateTo: '2026-03-31',
        ));
        $this->assertSame(self::MARCH, ['income' => $summary['income'], 'expense' => $summary['expense'], 'net' => $summary['net']]);

        // CSV export: income/expense rows (Type column) add up to the same totals.
        [$credit, $debit, $rows] = $this->csvIncomeExpenseTotals('2026-03-01', '2026-03-31');
        $this->assertSame(self::MARCH['income'], $credit);
        $this->assertSame(self::MARCH['expense'], $debit);
        $this->assertCount(3, $rows);

        // Analytics dashboard month comparison for the current month.
        $analytics = $this->pageProps($this->get(route('dashboard.analytics'))->assertOk())['chartData'];
        $this->assertSame('Mar 2026', $analytics['monthlyComparison'][0]['name']);
        $this->assertSame(self::MARCH['income'], (float) $analytics['monthlyComparison'][0]['income']);
        $this->assertSame(self::MARCH['expense'], (float) $analytics['monthlyComparison'][0]['expense']);
        $this->assertSame(self::MARCH['income'], (float) $analytics['transactionTypeDistribution'][0]['value']);
        $this->assertSame(self::MARCH['expense'], (float) $analytics['transactionTypeDistribution'][1]['value']);

        // AI tools agree with the web totals.
        $query = $this->decoded((new QueryExpensesTool)->execute('this_month', 'both'));
        $this->assertSame(['2,000.00', '450.00', '1,550.00'], [$query['income_total'], $query['expense_total'], $query['net']]);

        $search = $this->decoded((new SearchTransactionsTool)->execute('both', from_date: '2026-03-01', to_date: '2026-03-31'));
        $this->assertSame(3, $search['count']);
        $this->assertSame(['2,000.00', '450.00', '1,550.00'], [$search['income_total'], $search['expense_total'], $search['net']]);

        $insights = $this->decoded((new GetSpendingInsightsTool)->execute('spending_summary', 'this_month'));
        $this->assertSame([self::MARCH['income'], self::MARCH['expense'], self::MARCH['net']], [
            (float) $insights['income'], (float) $insights['expense'], (float) $insights['net'],
        ]);

        // MCP spending summary (expenses by category) adds up to the same expense total.
        $mcp = $this->mcpResult('spending_summary', ['period' => 'this_month']);
        $this->assertSame(self::MARCH['expense'], (float) array_sum(array_column($mcp, 'total')));

        // MCP list_transactions contract: March rows including the transfer pair.
        $listed = $this->mcpResult('list_transactions', ['period' => 'month', 'type' => 'all', 'limit' => 100]);
        $marchIds = array_column(array_filter($listed, fn (array $row) => substr($row['date'], 0, 7) === '2026-03'), 'id');
        $this->assertCount(5, $marchIds, 'March list must hold 3 income/expense rows plus the 2 transfer legs.');
        $this->assertSame(
            ['account', 'amount', 'category', 'date', 'description', 'id', 'type'],
            $this->sortedKeys($listed[0])
        );

        // Budgets: listing, detail, and the detail's transaction list agree.
        $budgetRow = $this->budgetRow($this->get(route('budgets.index'))->assertOk(), $this->budget->id);
        $this->assertSame(450.0, (float) $budgetRow['spent_amount']);

        $show = $this->pageProps($this->get(route('budgets.show', $this->budget))->assertOk());
        $this->assertSame(450.0, (float) $show['budget']['spent_amount']);
        $this->assertCount(2, $show['transactions']);
        $this->assertSame(450.0, round((float) collect($show['transactions'])->sum('amount'), 2));

        // AI budget_status reports the same spent figure for the March budget.
        $status = $this->decoded((new GetSpendingInsightsTool)->execute('budget_status', null, 'Food'));
        $this->assertSame(450.0, (float) $status['budgets'][0]['spent']);

        // Account balances: the AI tool and MCP agree. Main holds March net plus
        // the February expense (2000 - 999 - 300 - 150 - 500 = 51); savings 500.
        $balances = $this->decoded((new GetAccountBalancesTool)->execute());
        $mcpBalances = $this->mcpResult('get_balance', []);
        $this->assertSame((float) $balances['total_liquid_balance'], round((float) array_sum(array_column($mcpBalances, 'balance')), 2));
        $this->assertSame(551.0, (float) $balances['total_liquid_balance']);
        $this->assertSame(51.0, (float) $this->main->fresh()->current_balance);
        $this->assertSame(500.0, (float) $this->savings->fresh()->current_balance);
    }

    public function test_empty_ledger_reports_zero_on_every_surface(): void
    {
        // Deliberately empty state: every row deleted through the model so
        // balances revert and every surface must agree on zeros.
        foreach (Transaction::all() as $transaction) {
            $transaction->delete();
        }
        $this->assertSame(0, Transaction::count());

        $home = $this->pageProps($this->get(route('home'))->assertOk())['stats'];
        $this->assertSame(0.0, (float) $home['totalIncome']);
        $this->assertSame(0.0, (float) $home['totalExpenses']);
        $this->assertSame(0, (int) $home['totalTransactions']);

        $listing = $this->pageProps($this->get(route('transactions.index'))->assertOk());
        $this->assertSame(0, $listing['transactions']['total']);
        $this->assertSame(0.0, (float) $listing['totals']['total_income']);
        $this->assertSame(0.0, (float) $listing['totals']['total_expenses']);

        $summary = app(FinancialSummary::class)->totals(TransactionFilters::all());
        $this->assertSame(['income' => 0.0, 'expense' => 0.0, 'net' => 0.0, 'count' => 0], $summary);

        $query = $this->decoded((new QueryExpensesTool)->execute('all', 'both'));
        $this->assertSame(0, $query['count']);
        $this->assertSame(['0.00', '0.00', '0.00'], [$query['income_total'], $query['expense_total'], $query['net']]);

        $search = $this->decoded((new SearchTransactionsTool)->execute('both'));
        $this->assertSame(0, $search['count']);
        $this->assertSame('0.00', $search['total']);

        $insights = $this->decoded((new GetSpendingInsightsTool)->execute('spending_summary', 'all'));
        $this->assertSame([0.0, 0.0, 0.0], [(float) $insights['income'], (float) $insights['expense'], (float) $insights['net']]);

        $this->assertSame([], $this->mcpResult('spending_summary', ['period' => 'all']));

        // CSV export contract still holds: header row, no data rows.
        $csv = $this->get(route('transactions.export'))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Date', $lines[0]);

        // The budget still exists but nothing is spent against it anywhere.
        $budgetRow = $this->budgetRow($this->get(route('budgets.index'))->assertOk(), $this->budget->id);
        $this->assertSame(0.0, (float) $budgetRow['spent_amount']);

        $show = $this->pageProps($this->get(route('budgets.show', $this->budget))->assertOk());
        $this->assertSame(0.0, (float) $show['budget']['spent_amount']);
        $this->assertCount(0, $show['transactions']);

        $balances = $this->decoded((new GetAccountBalancesTool)->execute());
        $this->assertSame(0.0, (float) $balances['total_liquid_balance']);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function account(): Account
    {
        // Fixed savings type: factory types are random and credit cards are
        // excluded from the AI tool's liquid total, which would make the
        // AI-vs-MCP balance comparison depend on faker state.
        return Account::factory()->create([
            'type' => Account::TYPE_SAVINGS,
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);
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

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int|string, mixed>
     */
    private function mcpResult(string $tool, array $arguments): array
    {
        $response = app(McpServerCommand::class)->respond([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ]);

        $this->assertArrayHasKey('result', $response);

        return json_decode($response['result']['content'][0]['text'], true);
    }

    /**
     * @return array{0: float, 1: float, 2: list<array<string, string>>} [credit, debit, income/expense rows]
     */
    private function csvIncomeExpenseTotals(string $from, string $to): array
    {
        $csv = $this->get(route('transactions.export', ['date_from' => $from, 'date_to' => $to]))
            ->assertOk()
            ->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $header = str_getcsv(array_shift($lines));
        $typeIdx = array_search('Type', $header, true);
        $debitIdx = array_search('Debit', $header, true);
        $creditIdx = array_search('Credit', $header, true);
        $this->assertNotFalse($typeIdx);
        $this->assertNotFalse($debitIdx);
        $this->assertNotFalse($creditIdx);

        $credit = 0.0;
        $debit = 0.0;
        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            if (! in_array($cells[$typeIdx], [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE], true)) {
                continue;
            }
            $rows[] = $cells;
            $credit += (float) ($cells[$creditIdx] !== '' ? $cells[$creditIdx] : 0);
            $debit += (float) ($cells[$debitIdx] !== '' ? $cells[$debitIdx] : 0);
        }

        return [round($credit, 2), round($debit, 2), $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(TestResponse $response): array
    {
        $page = $response->viewData('page');

        if (is_array($page) && isset($page['props'])) {
            return $page['props'];
        }

        $content = $response->getOriginalContent();
        if (is_object($content) && method_exists($content, 'getData')) {
            $data = $content->getData();
            if (isset($data['page']['props'])) {
                return $data['page']['props'];
            }
        }

        $this->fail('Could not extract Inertia page props from the response.');

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetRow(TestResponse $response, int $budgetId): array
    {
        foreach ($this->pageProps($response)['budgets']['data'] ?? [] as $row) {
            if ((int) $row['id'] === $budgetId) {
                return $row;
            }
        }

        $this->fail("Budget {$budgetId} not found in the budgets listing.");

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function sortedKeys(array $row): array
    {
        $keys = array_keys($row);
        sort($keys);

        return $keys;
    }
}
