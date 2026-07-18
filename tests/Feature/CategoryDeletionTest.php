<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_with_transactions_cannot_be_deleted_or_change_account_balance(): void
    {
        $account = Account::factory()->create([
            'opening_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $category = Category::factory()->create(['parent_id' => null]);
        $transaction = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-07-18',
            'payment_method' => 'UPI',
        ]);
        $balanceBeforeDeletion = $account->fresh()->current_balance;
        $this->assertSame('900.00', $balanceBeforeDeletion);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'Cannot delete category. Reassign or delete its transactions first.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertSame($balanceBeforeDeletion, $account->fresh()->current_balance);
    }

    public function test_database_rejects_category_deletion_when_transactions_exist(): void
    {
        $account = Account::factory()->create([
            'opening_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $category = Category::factory()->create(['parent_id' => null]);
        $transaction = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'transaction_date' => '2026-07-18',
            'payment_method' => 'UPI',
        ]);

        try {
            DB::table('categories')->where('id', $category->id)->delete();
            $this->fail('Database allowed deletion of a category referenced by transactions.');
        } catch (QueryException) {
            // The foreign key is the concurrency-safe integrity backstop.
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertSame('900.00', $account->fresh()->current_balance);
    }

    public function test_empty_leaf_category_can_be_deleted(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Category deleted successfully.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_parent_category_with_children_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->delete(route('categories.destroy', $parent))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'Cannot delete category. It has sub-categories.');

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', ['id' => $child->id]);
    }
}
