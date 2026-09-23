<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeAndBalanceEdgesTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = 5;

    private const GROCERIES = 30;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    // ---------------------------------------------------------------------
    // #76 two-level category tree
    // ---------------------------------------------------------------------

    public function test_new_category_cannot_be_nested_under_a_child_category(): void
    {
        $this->post(route('categories.store'), [
            'name' => 'Organic',
            'code' => 'organic',
            'parent_id' => self::GROCERIES,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['code' => 'organic']);
    }

    public function test_category_cannot_be_moved_under_a_child_category(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);

        $this->put(route('categories.update', $category), [
            'name' => $category->name,
            'code' => $category->code,
            'parent_id' => self::GROCERIES,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_parent_with_children_cannot_become_a_child(): void
    {
        $food = Category::find(self::FOOD);

        $this->put(route('categories.update', $food), [
            'name' => $food->name,
            'code' => $food->code,
            'parent_id' => 2,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($food->fresh()->parent_id);
    }

    public function test_leaf_can_move_to_another_top_level_parent_and_parent_can_be_cleared(): void
    {
        $leaf = Category::factory()->create(['parent_id' => self::FOOD]);

        $this->put(route('categories.update', $leaf), [
            'name' => $leaf->name, 'code' => $leaf->code, 'parent_id' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $leaf->fresh()->parent_id);

        // The edit form always sends parent_id; an empty select arrives as null.
        $this->put(route('categories.update', $leaf), [
            'name' => $leaf->name, 'code' => $leaf->code, 'parent_id' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($leaf->fresh()->parent_id);
    }

    // ---------------------------------------------------------------------
    // #77 balance update after a category change
    // ---------------------------------------------------------------------

    public function test_changing_transfer_direction_category_updates_balance_with_the_new_category(): void
    {
        $account = $this->account(1000);
        $leg = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $this->categoryId(Transaction::CATEGORY_TRANSFER_OUTGOING),
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'amount' => 200,
            'transaction_date' => '2026-09-01',
        ]);
        $this->assertSame('800.00', $account->fresh()->current_balance);

        // The eager-loaded relation still points at TRANSFER_OUTGOING when this saves.
        $leg->update(['category_id' => $this->categoryId(Transaction::CATEGORY_TRANSFER_INCOMING)]);

        $this->assertSame('1200.00', $account->fresh()->current_balance);
        $this->assertSame('1200.00', number_format($account->fresh()->recalculateBalance(), 2, '.', ''));
    }

    // ---------------------------------------------------------------------
    // Account balance edits
    // ---------------------------------------------------------------------

    public function test_new_account_current_balance_always_equals_opening_balance(): void
    {
        $this->post(route('accounts.store'), [
            'code' => 'CC1', 'name' => 'Card', 'type' => 'credit_card',
            'opening_balance' => -2500, 'current_balance' => 999999,
        ])->assertSessionHasNoErrors();

        $account = Account::where('code', 'CC1')->sole();
        $this->assertSame('-2500.00', $account->opening_balance);
        $this->assertSame('-2500.00', $account->current_balance);
    }

    public function test_editing_opening_balance_shifts_current_balance_and_ignores_direct_edits(): void
    {
        $account = $this->account(1000);
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => self::GROCERIES,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 300,
            'transaction_date' => '2026-09-01',
            'payment_method' => 'UPI',
        ]);

        $this->put(route('accounts.update', $account), [
            'code' => $account->code, 'name' => $account->name, 'type' => $account->type,
            'opening_balance' => 1500, 'current_balance' => 123456,
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('1500.00', $account->opening_balance);
        $this->assertSame('1200.00', $account->current_balance);
        $this->assertSame(1200.0, $account->recalculateBalance());
    }

    // ---------------------------------------------------------------------
    // Hostile query strings
    // ---------------------------------------------------------------------

    public function test_non_numeric_page_parameters_do_not_crash_listing_pages(): void
    {
        $this->get('/categories?page=abc&per_page=zzz')->assertOk();
        $this->get('/categories?page=0')->assertOk();
        $this->get('/budgets?per_page=abc')->assertOk();
    }

    private function account(float $balance): Account
    {
        return Account::factory()->create([
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function categoryId(string $code): int
    {
        return Category::where('code', $code)->value('id');
    }
}
