<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Issue #64: category and budget pages must compute their aggregates in a
 * bounded number of SQL queries, with every number the user sees unchanged.
 */
class AggregateQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = 5;

    private const GROCERIES = 30;

    private const RESTAURANT = 32;

    private const SNACK = 33;

    private const SALARY = 84;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->account = Account::factory()->create([
            'opening_balance' => 100000,
            'current_balance' => 100000,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Correctness: category totals
    // ---------------------------------------------------------------------

    public function test_category_index_totals_include_children_and_all_transaction_types(): void
    {
        // Category totals keep their long-standing meaning: EVERY transaction
        // type counts, including income rows and transfer legs.
        $this->transaction(100.50, '2026-09-10', self::GROCERIES);          // expense on child
        $this->transaction(25.25, '2026-09-12', self::FOOD);                // expense on parent
        $this->transaction(50, '2026-09-14', self::RESTAURANT);             // expense on sibling child
        $this->transaction(700, '2026-09-12', self::GROCERIES, Transaction::TYPE_INCOME);   // income under child
        $this->transaction(300, '2026-09-13', self::GROCERIES, Transaction::TYPE_TRANSFER); // transfer leg under child

        $response = $this->get(route('categories.index', ['per_page' => 20]));

        // Food (parent): own 25.25 + Groceries (100.50 + 700 + 300) + Restaurant 50.
        $food = $this->categoryRow($response, self::FOOD);
        $this->assertSame(1175.75, $food['total_amount_with_children']);
        $this->assertSame(5, $food['transaction_count_with_children']);
        $this->assertSame(1175.75, $food['total_amount']);
        $this->assertSame(5, $food['transactions_count']);

        // Groceries (child) shows only its own rows.
        $groceries = $this->categoryRow($response, self::GROCERIES);
        $this->assertSame(1100.50, $groceries['total_amount']);
        $this->assertSame(3, $groceries['transactions_count']);
    }

    public function test_category_show_has_parent_totals_and_per_child_breakdown(): void
    {
        $this->transaction(100.50, '2026-09-10', self::GROCERIES);
        $this->transaction(25.25, '2026-09-12', self::FOOD);
        $this->transaction(50, '2026-09-14', self::RESTAURANT);
        $this->transaction(300, '2026-09-13', self::GROCERIES, Transaction::TYPE_TRANSFER);

        $response = $this->get(route('categories.show', self::FOOD));

        $stats = $this->pageProps($response)['stats'];
        $this->assertSame(475.75, $stats['total_amount']);
        $this->assertSame(4, $stats['transaction_count']);

        $byChild = collect($stats['children_stats'])->keyBy('id');
        $this->assertSame(400.50, $byChild[self::GROCERIES]['total_amount']);
        $this->assertSame(2, $byChild[self::GROCERIES]['transaction_count']);
        $this->assertSame(50.0, $byChild[self::RESTAURANT]['total_amount']);
        $this->assertSame(1, $byChild[self::RESTAURANT]['transaction_count']);
    }

    public function test_child_category_show_has_only_its_own_totals(): void
    {
        $this->transaction(100.50, '2026-09-10', self::GROCERIES);
        $this->transaction(25.25, '2026-09-12', self::FOOD);

        $response = $this->get(route('categories.show', self::GROCERIES));

        $stats = $this->pageProps($response)['stats'];
        $this->assertSame(100.50, $stats['total_amount']);
        $this->assertSame(1, $stats['transaction_count']);
        $this->assertCount(0, $stats['children_stats']);
    }

    // ---------------------------------------------------------------------
    // Correctness: budget aggregates
    // ---------------------------------------------------------------------

    public function test_budget_spent_respects_boundaries_children_and_expense_only(): void
    {
        $budget = $this->budget(self::FOOD, 500, '2026-09-01', '2026-09-30');

        $this->transaction(200, '2026-09-01', self::GROCERIES);                 // start boundary, child
        $this->transaction(100.50, '2026-09-30', self::RESTAURANT);             // end boundary, child
        $this->transaction(25, '2026-09-10', self::FOOD);                       // own category
        $this->transaction(50, '2026-09-15', self::SNACK);                      // another child
        $this->transaction(60, '2026-08-31', self::RESTAURANT);                 // before start: excluded
        $this->transaction(120, '2026-10-01', self::GROCERIES);                 // after end: excluded
        $this->transaction(700, '2026-09-12', self::GROCERIES, Transaction::TYPE_INCOME);     // income: excluded
        $this->transaction(300, '2026-09-13', self::SNACK, Transaction::TYPE_TRANSFER);       // transfer: excluded

        // A second budget on a child category, close to its limit.
        $smallBudget = $this->budget(self::RESTAURANT, 100, '2026-09-01', '2026-09-30');

        $response = $this->get(route('budgets.index'));

        $spent = $this->budgetRow($response, $budget->id);
        $this->assertSame(375.50, $spent['spent_amount']);
        $this->assertSame(124.50, $spent['remaining_amount']);
        $this->assertSame(75.1, $spent['percentage_used']);
        $this->assertSame('success', $spent['status']);

        $exceeded = $this->budgetRow($response, $smallBudget->id);
        $this->assertSame(100.50, $exceeded['spent_amount']);
        // min(100, …) caps at int 100; max(0, …) floors at int 0 — both match the original code.
        $this->assertSame(0, $exceeded['remaining_amount']);
        $this->assertSame(100, $exceeded['percentage_used']);
        $this->assertSame('danger', $exceeded['status']);
    }

    public function test_budget_show_returns_the_same_aggregates_as_the_listing(): void
    {
        $budget = $this->budget(self::FOOD, 500, '2026-09-01', '2026-09-30');

        $this->transaction(200, '2026-09-01', self::GROCERIES);
        $this->transaction(100.50, '2026-09-30', self::RESTAURANT);

        $response = $this->get(route('budgets.show', $budget));

        $page = $this->pageProps($response);
        $this->assertSame(300.50, $page['budget']['spent_amount']);
        $this->assertSame(199.50, $page['budget']['remaining_amount']);
        $this->assertSame(60.1, $page['budget']['percentage_used']);
        $this->assertSame('success', $page['budget']['status']);
    }

    // ---------------------------------------------------------------------
    // Bounded queries
    // ---------------------------------------------------------------------

    public function test_categories_page_query_count_does_not_grow_with_rows(): void
    {
        $this->buildCategoryTrees(3);
        $with3Parents = $this->queryCountFor(route('categories.index', ['per_page' => 20]));

        $this->buildCategoryTrees(9); // 12 parent categories in total
        $with12Parents = $this->queryCountFor(route('categories.index', ['per_page' => 20]));

        $this->assertSame(
            $with3Parents,
            $with12Parents,
            "GET /categories query count grew with rows: 3 parents => {$with3Parents}, 12 parents => {$with12Parents}"
        );
    }

    public function test_budgets_page_query_count_does_not_grow_with_rows(): void
    {
        $this->buildBudgets(2);
        $with2Budgets = $this->queryCountFor(route('budgets.index'));

        $this->buildBudgets(8); // 10 budgets in total
        $with10Budgets = $this->queryCountFor(route('budgets.index'));

        $this->assertSame(
            $with2Budgets,
            $with10Budgets,
            "GET /budgets query count grew with rows: 2 budgets => {$with2Budgets}, 10 budgets => {$with10Budgets}"
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function transaction(float $amount, string $date, int $categoryId, string $type = Transaction::TYPE_EXPENSE): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'category_id' => $categoryId,
            'transaction_type' => $type,
            'amount' => $amount,
            'transaction_date' => $date,
        ]);
    }

    private function budget(int $categoryId, float $amount, string $start, string $end): Budget
    {
        return Budget::create([
            'category_id' => $categoryId,
            'name' => "Budget for category {$categoryId}",
            'amount' => $amount,
            'period_type' => Budget::PERIOD_CUSTOM,
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => true,
        ]);
    }

    /**
     * $parents parent categories, each with two children and rows on the
     * parent, a child, and an income row on the second child.
     */
    private function buildCategoryTrees(int $parents): void
    {
        foreach (range(1, $parents) as $i) {
            $parent = Category::factory()->parent()->create();
            $childA = Category::factory()->create(['parent_id' => $parent->id]);
            $childB = Category::factory()->create(['parent_id' => $parent->id]);

            $this->transaction(100, "2026-09-0{$i}", $childA->id);
            $this->transaction(50, "2026-09-1{$i}", $parent->id);
            $this->transaction(25, "2026-09-2{$i}", $childB->id, Transaction::TYPE_INCOME);
        }
    }

    /**
     * $count parent-category budgets, each with a child and an expense in range.
     */
    private function buildBudgets(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $parent = Category::factory()->parent()->create();
            $child = Category::factory()->create(['parent_id' => $parent->id]);

            $this->transaction(10 * $i, '2026-09-15', $child->id);
            $this->budget($parent->id, 1000, '2026-09-01', '2026-09-30');
        }
    }

    private function queryCountFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

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
    private function categoryRow(TestResponse $response, int $categoryId): array
    {
        foreach ($this->pageProps($response)['categories']['data'] ?? [] as $row) {
            if ((int) $row['id'] === $categoryId) {
                return $row;
            }
        }

        $this->fail("Category {$categoryId} not found in the categories listing.");

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
}
