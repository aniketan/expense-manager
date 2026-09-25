<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\AiCategorization\CategoryMatcher;
use App\Services\AiCategorization\CategoryTreeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function matcher(): CategoryMatcher
    {
        return new CategoryMatcher(new CategoryTreeResolver);
    }

    public function test_score_prefers_credit_card_candidate_for_card_payment_wording(): void
    {
        $matcher = $this->matcher();

        $card = $matcher->scoreExpenseDescriptionAgainstCandidate(
            'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
            'Loans',
            'Credit Card'
        );
        $mortgage = $matcher->scoreExpenseDescriptionAgainstCandidate(
            'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
            'Loans',
            'Mortgage'
        );

        $this->assertGreaterThanOrEqual(32, $card);
        $this->assertGreaterThan($mortgage, $card);
    }

    public function test_score_matches_mortgage_and_home_loan_wording(): void
    {
        $matcher = $this->matcher();

        $this->assertGreaterThanOrEqual(
            32,
            $matcher->scoreExpenseDescriptionAgainstCandidate('HOME LOAN EMI PAYMENT', 'Loans', 'Mortgage')
        );
        $this->assertGreaterThanOrEqual(
            32,
            $matcher->scoreExpenseDescriptionAgainstCandidate('MORTGAGE DEDUCTION', 'Housing', 'Mortgage')
        );
    }

    public function test_score_matches_student_loan_wording(): void
    {
        $this->assertGreaterThanOrEqual(
            32,
            $this->matcher()->scoreExpenseDescriptionAgainstCandidate('STUDENT LOAN EMI', 'Loans', 'Student Loan')
        );
    }

    public function test_score_matches_auto_loan_wording(): void
    {
        $this->assertGreaterThanOrEqual(
            32,
            $this->matcher()->scoreExpenseDescriptionAgainstCandidate('AUTO LOAN EMI DEDUCTED', 'Loans', 'Auto Loan')
        );
    }

    public function test_score_skips_noise_bank_tokens(): void
    {
        $matcher = $this->matcher();

        $plain = $matcher->scoreExpenseDescriptionAgainstCandidate('CREDIT CARD PAYMENT', 'Loans', 'Credit Card');
        $noisy = $matcher->scoreExpenseDescriptionAgainstCandidate(
            'HDFC UPI CREDIT CARD PAYMENT VIA NEFTRTGSIMPS',
            'Loans',
            'Credit Card'
        );

        $this->assertSame($plain, $noisy);
    }

    public function test_resolve_expense_category_from_description_returns_heuristic_match(): void
    {
        $loans = Category::factory()->parent()->create(['name' => 'Loans', 'code' => 'LN', 'is_active' => true]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'is_active' => true,
        ]);

        $tree = [
            [
                'id' => $loans->id,
                'name' => 'Loans',
                'children' => [
                    ['id' => $creditCard->id, 'name' => 'Credit Card'],
                ],
            ],
        ];

        $resolved = $this->matcher()->resolveExpenseCategoryFromDescription(
            'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
            $tree
        );

        $this->assertNotNull($resolved);
        $this->assertSame($loans->id, $resolved['category_id']);
        $this->assertSame($creditCard->id, $resolved['subcategory_id']);
        $this->assertFalse($resolved['fallback']);
    }

    public function test_resolve_expense_category_from_description_returns_null_below_threshold(): void
    {
        $loans = Category::factory()->parent()->create(['name' => 'Loans', 'code' => 'LN2', 'is_active' => true]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'is_active' => true,
        ]);

        $tree = [
            [
                'id' => $loans->id,
                'name' => 'Loans',
                'children' => [
                    ['id' => $creditCard->id, 'name' => 'Credit Card'],
                ],
            ],
        ];

        $this->assertNull($this->matcher()->resolveExpenseCategoryFromDescription('RANDOM XYZ ABC', $tree));
    }

    public function test_refine_swaps_wrong_sibling_and_clears_fallback(): void
    {
        $loans = Category::factory()->parent()->create(['name' => 'Loans', 'code' => 'LN3', 'is_active' => true]);
        $dummy = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Alphabetical First Dummy',
            'is_active' => true,
        ]);
        $creditCard = Category::factory()->create([
            'parent_id' => $loans->id,
            'name' => 'Credit Card',
            'is_active' => true,
        ]);

        $refined = $this->matcher()->refineExpenseSiblingByDescription(
            'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
            ['category_id' => $loans->id, 'subcategory_id' => $dummy->id, 'fallback' => true]
        );

        $this->assertSame($loans->id, $refined['category_id']);
        $this->assertSame($creditCard->id, $refined['subcategory_id']);
        $this->assertFalse($refined['fallback']);
    }
}
