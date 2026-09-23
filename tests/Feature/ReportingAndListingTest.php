<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportingAndListingTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = 5;

    private const GROCERIES = 30;

    private const RESTAURANT = 32;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->account = Account::factory()->create([
            'opening_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Transaction list
    // ---------------------------------------------------------------------

    public function test_date_to_filter_includes_the_last_day_in_list_and_totals(): void
    {
        $this->expense(100, '2026-09-29');
        $this->expense(250, '2026-09-30');
        $this->expense(999, '2026-10-01');

        $this->get(route('transactions.index', ['date_from' => '2026-09-29', 'date_to' => '2026-09-30']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.total', 2)
                ->where('totals.total_expenses', fn ($total) => (float) $total === 350.0));
    }

    public function test_date_to_filter_includes_the_last_day_in_csv_export(): void
    {
        $this->expense(250, '2026-09-30', 'Last day of range');

        $csv = $this->get(route('transactions.export', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Last day of range', $csv);
    }

    public function test_per_page_from_the_query_string_is_respected(): void
    {
        foreach (range(1, 30) as $day) {
            $this->expense(10, '2026-08-01');
        }

        $this->get('/transactions?per_page=25')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.per_page', 25)
                ->has('transactions.data', 25));
    }

    public function test_search_combined_with_category_sort_orders_by_category_name(): void
    {
        $this->expense(100, '2026-09-01', 'Weekly shop', self::RESTAURANT);
        $this->expense(200, '2026-09-02', 'Weekly shop', self::GROCERIES);

        $this->get(route('transactions.index', ['search' => 'weekly', 'sort_by' => 'category']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('transactions.data.0.category_id', self::GROCERIES)
                ->where('transactions.data.1.category_id', self::RESTAURANT));
    }

    // ---------------------------------------------------------------------
    // Budget alerts
    // ---------------------------------------------------------------------

    public function test_budget_alerts_reach_the_transactions_page(): void
    {
        $this->budget(self::GROCERIES, 500, '2026-09-01', '2026-09-30');

        $this->post(route('transactions.store'), $this->expensePayload(600, '2026-09-15', self::GROCERIES))
            ->assertRedirect(route('transactions.index'));

        $this->get(route('transactions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('budget_alerts', 1)
                ->where('budget_alerts.0.type', 'danger'));
    }

    public function test_parent_category_budget_alerts_on_child_expense_on_its_first_day(): void
    {
        $this->budget(self::FOOD, 500, '2026-09-01', '2026-09-30');

        $this->post(route('transactions.store'), $this->expensePayload(450, '2026-09-01', self::GROCERIES))
            ->assertSessionHas('budget_alerts', fn (array $alerts) => count($alerts) === 1 && $alerts[0]['type'] === 'warning');
    }

    // ---------------------------------------------------------------------
    // Budgets
    // ---------------------------------------------------------------------

    public function test_parent_budget_detail_lists_child_transactions_matching_spent_total(): void
    {
        $budget = $this->budget(self::FOOD, 5000, '2026-09-01', '2026-09-30');
        $this->expense(100, '2026-09-05', 'Veg', self::GROCERIES);
        $this->expense(300, '2026-09-30', 'Dinner', self::RESTAURANT);
        $this->expense(999, '2026-10-01', 'Outside period', self::GROCERIES);

        $this->get(route('budgets.show', $budget))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions', 2)
                ->where('budget.spent_amount', fn ($spent) => (float) $spent === 400.0));
    }

    public function test_overlapping_budget_is_rejected_when_ranges_touch_on_a_boundary_day(): void
    {
        $this->budget(self::GROCERIES, 500, '2026-09-01', '2026-09-30');

        $this->post(route('budgets.store'), [
            'category_id' => self::GROCERIES,
            'name' => 'Ends on the existing budget first day',
            'amount' => 300,
            'period_type' => Budget::PERIOD_CUSTOM,
            'start_date' => '2026-08-15',
            'end_date' => '2026-09-01',
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(1, Budget::count());
    }

    public function test_budget_ending_today_is_still_current(): void
    {
        Carbon::setTestNow('2026-09-30 18:45:00');
        $budget = $this->budget(self::GROCERIES, 500, '2026-09-01', '2026-09-30');

        $this->assertTrue(Budget::current()->whereKey($budget->id)->exists());

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function expense(float $amount, string $date, string $description = 'Expense', int $categoryId = self::GROCERIES): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date,
            'payment_method' => 'UPI',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(float $amount, string $date, int $categoryId): array
    {
        return [
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => $amount,
            'transaction_date' => $date,
            'payment_method' => 'UPI',
        ];
    }

    private function budget(int $categoryId, float $amount, string $start, string $end): Budget
    {
        return Budget::create([
            'category_id' => $categoryId,
            'name' => 'Budget',
            'amount' => $amount,
            'period_type' => Budget::PERIOD_CUSTOM,
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => true,
        ]);
    }
}
