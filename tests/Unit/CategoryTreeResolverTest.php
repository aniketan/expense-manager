<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\AiCategorization\CategoryTreeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): CategoryTreeResolver
    {
        return new CategoryTreeResolver;
    }

    public function test_normalize_income_accepts_valid_income_child(): void
    {
        $incomeRoot = Category::factory()->parent()->create([
            'code' => 'income',
            'name' => 'Income',
            'is_active' => true,
        ]);
        $salary = Category::factory()->create([
            'parent_id' => $incomeRoot->id,
            'name' => 'Salary',
            'is_active' => true,
        ]);

        $normalized = $this->resolver()->normalizeToTreeIds($incomeRoot->id, $salary->id, 'income');

        $this->assertNotNull($normalized);
        $this->assertSame($incomeRoot->id, $normalized['category_id']);
        $this->assertSame($salary->id, $normalized['subcategory_id']);
        $this->assertFalse($normalized['fallback']);
    }

    public function test_normalize_income_falls_back_to_first_child_for_invalid_ids(): void
    {
        $incomeRoot = Category::factory()->parent()->create([
            'code' => 'INCOME',
            'name' => 'Income',
            'is_active' => true,
        ]);
        $salary = Category::factory()->create([
            'parent_id' => $incomeRoot->id,
            'name' => 'Salary',
            'is_active' => true,
        ]);

        $normalized = $this->resolver()->normalizeToTreeIds(999999, 888888, 'income');

        $this->assertNotNull($normalized);
        $this->assertSame($incomeRoot->id, $normalized['category_id']);
        $this->assertSame($salary->id, $normalized['subcategory_id']);
        $this->assertTrue($normalized['fallback']);
    }

    public function test_normalize_expense_accepts_valid_expense_child(): void
    {
        $food = Category::factory()->parent()->create(['name' => 'Food', 'code' => 'FD', 'is_active' => true]);
        $groceries = Category::factory()->create([
            'parent_id' => $food->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        $normalized = $this->resolver()->normalizeToTreeIds($food->id, $groceries->id, 'expense');

        $this->assertNotNull($normalized);
        $this->assertSame($food->id, $normalized['category_id']);
        $this->assertSame($groceries->id, $normalized['subcategory_id']);
        $this->assertFalse($normalized['fallback']);
    }

    public function test_normalize_expense_rejects_income_child_as_fallback(): void
    {
        $incomeRoot = Category::factory()->parent()->create([
            'code' => 'income',
            'name' => 'Income',
            'is_active' => true,
        ]);
        $salary = Category::factory()->create([
            'parent_id' => $incomeRoot->id,
            'name' => 'Salary',
            'is_active' => true,
        ]);

        $food = Category::factory()->parent()->create(['name' => 'Food', 'code' => 'FD', 'is_active' => true]);
        $groceries = Category::factory()->create([
            'parent_id' => $food->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        $normalized = $this->resolver()->normalizeToTreeIds($incomeRoot->id, $salary->id, 'expense');

        $this->assertNotNull($normalized);
        $this->assertSame($food->id, $normalized['category_id']);
        $this->assertSame($groceries->id, $normalized['subcategory_id']);
        $this->assertTrue($normalized['fallback']);
    }

    public function test_normalize_expense_rejects_account_transfer_child_as_fallback(): void
    {
        $transferRoot = Category::factory()->parent()->create([
            'code' => 'ACCOUNT_TRANSFER',
            'name' => 'Account Transfer',
            'is_active' => true,
        ]);
        $transferChild = Category::factory()->create([
            'parent_id' => $transferRoot->id,
            'name' => 'Account Transfer',
            'is_active' => true,
        ]);

        $food = Category::factory()->parent()->create(['name' => 'Food', 'code' => 'FD2', 'is_active' => true]);
        $groceries = Category::factory()->create([
            'parent_id' => $food->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        $normalized = $this->resolver()->normalizeToTreeIds($transferRoot->id, $transferChild->id, 'expense');

        $this->assertNotNull($normalized);
        $this->assertSame($food->id, $normalized['category_id']);
        $this->assertSame($groceries->id, $normalized['subcategory_id']);
        $this->assertTrue($normalized['fallback']);
    }

    public function test_is_non_expense_root(): void
    {
        $resolver = $this->resolver();

        $income = Category::factory()->parent()->create(['code' => 'income', 'name' => 'Income']);
        $transfer = Category::factory()->parent()->create(['code' => 'ACCOUNT_TRANSFER', 'name' => 'Account Transfer']);
        $food = Category::factory()->parent()->create(['code' => 'FD3', 'name' => 'Food']);

        $this->assertTrue($resolver->isNonExpenseRoot($income));
        $this->assertTrue($resolver->isNonExpenseRoot($transfer));
        $this->assertFalse($resolver->isNonExpenseRoot($food));
    }
}
