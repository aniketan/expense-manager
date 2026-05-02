<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class AiCategorizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_categorize_validates_input(): void
    {
        $response = $this->postJson('/ai/categorize', []);

        $response->assertStatus(422);
    }

    public function test_categorize_returns_normalized_ids_from_ai_json(): void
    {
        $parent = Category::factory()->parent()->create(['name' => 'Food', 'code' => 'FOO', 'is_active' => true]);
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'category_id' => $parent->id,
                    'subcategory_id' => $child->id,
                    'confidence' => 'high',
                    'reason' => 'Grocery store',
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'DMART GROCERIES',
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $parent->id)
            ->assertJsonPath('subcategory_id', $child->id)
            ->assertJsonPath('confidence', 'high');
    }

    public function test_categorize_income_returns_income_subtree_ids(): void
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

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'category_id' => $incomeRoot->id,
                    'subcategory_id' => $salary->id,
                    'confidence' => 'medium',
                    'reason' => 'Payroll deposit',
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'SALARY CREDIT',
            'type' => 'income',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $incomeRoot->id)
            ->assertJsonPath('subcategory_id', $salary->id)
            ->assertJsonPath('confidence', 'medium');
    }

    public function test_categorize_expense_falls_back_when_ai_returns_income_child(): void
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

        $food = Category::factory()->parent()->create([
            'name' => 'Food',
            'code' => 'FD',
            'is_active' => true,
        ]);
        $groceries = Category::factory()->create([
            'parent_id' => $food->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'category_id' => $incomeRoot->id,
                    'subcategory_id' => $salary->id,
                    'confidence' => 'low',
                    'reason' => 'Wrong branch',
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'STORE',
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $food->id)
            ->assertJsonPath('subcategory_id', $groceries->id);
    }

    public function test_categorize_expense_maps_credit_card_payment_when_model_returns_no_json(): void
    {
        $loans = Category::factory()->parent()->create([
            'name' => 'Loans',
            'code' => 'LN',
            'is_active' => true,
        ]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'code' => 'CC_PAY',
            'is_active' => true,
        ]);

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: '',
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'INDUSIND CREDIT CARD PAYMENT/XXXXXXXXXXXX6183',
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $loans->id)
            ->assertJsonPath('subcategory_id', $creditCard->id)
            ->assertJsonPath('confidence', 'medium');
    }

    public function test_categorize_expense_refines_subcategory_when_only_parent_is_from_model(): void
    {
        $loans = Category::factory()->parent()->create([
            'name' => 'Loans',
            'code' => 'LN2',
            'is_active' => true,
        ]);
        Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Alphabetical First Dummy',
            'code' => 'ZZZ',
            'is_active' => true,
        ]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'code' => 'CC_PAY2',
            'is_active' => true,
        ]);

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'category_id' => $loans->id,
                    'confidence' => 'low',
                    'reason' => 'Loans umbrella only',
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'INDUSIND CREDIT CARD PAYMENT/XXXXXXXXXXXX6183',
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $loans->id)
            ->assertJsonPath('subcategory_id', $creditCard->id);
    }

    public function test_categorize_expense_refines_subcategory_when_model_picks_wrong_sibling_under_loans(): void
    {
        $loans = Category::factory()->parent()->create([
            'name' => 'Loans',
            'code' => 'LN3',
            'is_active' => true,
        ]);
        $mortgage = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Mortgage',
            'code' => 'MORT',
            'is_active' => true,
        ]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'code' => 'CC_PAY3',
            'is_active' => true,
        ]);

        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'category_id' => $loans->id,
                    'subcategory_id' => $mortgage->id,
                    'confidence' => 'high',
                    'reason' => 'Loan related',
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $response = $this->postJson('/ai/categorize', [
            'description' => 'INDUSIND CREDIT CARD PAYMENT/XXXXXXXXXXXX6183',
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertJsonPath('category_id', $loans->id)
            ->assertJsonPath('subcategory_id', $creditCard->id);
    }
}
