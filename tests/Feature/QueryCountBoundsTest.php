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
 * Issue #66 / #64: category and budget pages must compute their aggregates in
 * a bounded number of SQL queries. The neighbor AggregateQueryCountTest pins
 * N-invariance (more rows => same count); here every page also gets an
 * absolute upper bound tied to a fixed constant, so a middleware/auth change
 * that adds one query does not silently re-baseline an N+1 regression.
 */
class QueryCountBoundsTest extends TestCase
{
    use RefreshDatabase;

    // Fixed ceilings with headroom over today's measured counts (categories
    // index ~7, budgets index ~7, shows ~6 under phpunit's in-memory SQLite).
    // If a page legitimately needs more, raise the constant deliberately.
    private const MAX_CATEGORIES_INDEX_QUERIES = 20;

    private const MAX_BUDGETS_INDEX_QUERIES = 20;

    private const MAX_CATEGORY_SHOW_QUERIES = 20;

    private const MAX_BUDGET_SHOW_QUERIES = 20;

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

    public function test_categories_index_stays_within_fixed_query_budget_with_correct_totals(): void
    {
        // Named to sort first: the index paginates parent groups, so a random
        // factory name could land on page two and hide the assertion row.
        $parent = Category::factory()->parent()->create(['name' => 'AAA Bounds Parent']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->transaction(100, '2026-09-10', $child->id);
        $this->transaction(50, '2026-09-11', $parent->id);
        $this->buildCategoryTrees(11); // 12 parent trees in total

        [$count, $response] = $this->getWithQueryCount(route('categories.index', ['per_page' => 20]));

        $this->assertLessThanOrEqual(
            self::MAX_CATEGORIES_INDEX_QUERIES, $count,
            "GET /categories ran {$count} queries, above the fixed budget of ".self::MAX_CATEGORIES_INDEX_QUERIES
        );

        $row = $this->categoryRow($response, $parent->id);
        $this->assertSame(150.0, $row['total_amount_with_children']);
        $this->assertSame(2, $row['transaction_count_with_children']);
    }

    public function test_budgets_index_stays_within_fixed_query_budget_with_correct_totals(): void
    {
        $parent = Category::factory()->parent()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->transaction(200, '2026-09-15', $child->id);
        $budget = $this->budget($parent->id, 1000, '2026-09-01', '2026-09-30');
        $this->buildBudgets(9); // 10 budgets in total

        [$count, $response] = $this->getWithQueryCount(route('budgets.index'));

        $this->assertLessThanOrEqual(
            self::MAX_BUDGETS_INDEX_QUERIES, $count,
            "GET /budgets ran {$count} queries, above the fixed budget of ".self::MAX_BUDGETS_INDEX_QUERIES
        );

        $this->assertSame(200.0, $this->budgetRow($response, $budget->id)['spent_amount']);
    }

    public function test_category_show_stays_within_fixed_query_budget(): void
    {
        $parent = Category::factory()->parent()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->transaction(100, '2026-09-10', $child->id);
        $this->transaction(50, '2026-09-11', $parent->id);

        [$count, $response] = $this->getWithQueryCount(route('categories.show', $parent->id));

        $this->assertLessThanOrEqual(
            self::MAX_CATEGORY_SHOW_QUERIES, $count,
            "GET /categories/{$parent->id} ran {$count} queries, above the fixed budget of ".self::MAX_CATEGORY_SHOW_QUERIES
        );

        $stats = $this->pageProps($response)['stats'];
        $this->assertSame(150.0, $stats['total_amount']);
        $this->assertSame(2, $stats['transaction_count']);
    }

    public function test_budget_show_stays_within_fixed_query_budget(): void
    {
        $parent = Category::factory()->parent()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->transaction(200, '2026-09-15', $child->id);
        $budget = $this->budget($parent->id, 1000, '2026-09-01', '2026-09-30');

        [$count, $response] = $this->getWithQueryCount(route('budgets.show', $budget));

        $this->assertLessThanOrEqual(
            self::MAX_BUDGET_SHOW_QUERIES, $count,
            "GET /budgets/{$budget->id} ran {$count} queries, above the fixed budget of ".self::MAX_BUDGET_SHOW_QUERIES
        );

        $this->assertSame(200.0, $this->pageProps($response)['budget']['spent_amount']);
    }

    public function test_adding_rows_adds_no_queries_on_either_listing(): void
    {
        // Failure path for an N+1 regression: growing rows must not grow queries,
        // and both measurements must sit under the same fixed ceiling.
        $this->buildCategoryTrees(3);
        [$smallCategories] = $this->getWithQueryCount(route('categories.index', ['per_page' => 20]));

        $this->buildCategoryTrees(9); // 12 parent trees in total
        [$largeCategories] = $this->getWithQueryCount(route('categories.index', ['per_page' => 20]));

        $this->assertSame($smallCategories, $largeCategories,
            "GET /categories query count grew with rows: 3 parents => {$smallCategories}, 12 parents => {$largeCategories}");
        $this->assertLessThanOrEqual(self::MAX_CATEGORIES_INDEX_QUERIES, $largeCategories);

        $this->buildBudgets(2);
        [$smallBudgets] = $this->getWithQueryCount(route('budgets.index'));

        $this->buildBudgets(8); // 10 budgets in total
        [$largeBudgets] = $this->getWithQueryCount(route('budgets.index'));

        $this->assertSame($smallBudgets, $largeBudgets,
            "GET /budgets query count grew with rows: 2 budgets => {$smallBudgets}, 10 budgets => {$largeBudgets}");
        $this->assertLessThanOrEqual(self::MAX_BUDGETS_INDEX_QUERIES, $largeBudgets);
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
     * $parents parent categories, each with two children and one expense.
     */
    private function buildCategoryTrees(int $parents): void
    {
        foreach (range(1, $parents) as $i) {
            $parent = Category::factory()->parent()->create();
            $childA = Category::factory()->create(['parent_id' => $parent->id]);
            Category::factory()->create(['parent_id' => $parent->id]);

            $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $this->transaction(100, "2026-09-{$day}", $childA->id);
            $this->transaction(50, "2026-09-{$day}", $parent->id);
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

    /**
     * @return array{0: int, 1: TestResponse}
     */
    private function getWithQueryCount(string $uri): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get($uri);
        $response->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$count, $response];
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
