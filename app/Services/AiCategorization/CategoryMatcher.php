<?php

namespace App\Services\AiCategorization;

use App\Models\Category;

class CategoryMatcher
{
    private const EXPENSE_DESCRIPTION_MATCH_MIN_SCORE = 32;

    /** Margin required to swap to a sibling (model/parent-first picked wrong leaf). */
    private const EXPENSE_REFINE_MARGIN = 24;

    public function __construct(
        private CategoryTreeResolver $treeResolver,
    ) {}

    /**
     * Pick best expense leaf by overlap between transaction text and category names (handles empty LLM output).
     *
     * A wording match determines the leaf itself, so it is never a default fallback.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array{category_id: int, subcategory_id: int, fallback: bool}|null
     */
    public function resolveExpenseCategoryFromDescription(string $description, array $tree): ?array
    {
        $bestScore = 0;
        $bestParentId = null;
        $bestChildId = null;

        foreach ($tree as $parent) {
            $parentId = (int) ($parent['id'] ?? 0);
            $parentName = (string) ($parent['name'] ?? '');
            foreach (($parent['children'] ?? []) as $child) {
                $childId = (int) ($child['id'] ?? 0);
                $childName = (string) ($child['name'] ?? '');
                if ($parentId === 0 || $childId === 0) {
                    continue;
                }
                $score = $this->scoreExpenseDescriptionAgainstCandidate($description, $parentName, $childName);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestParentId = $parentId;
                    $bestChildId = $childId;
                }
            }
        }

        if ($bestScore < self::EXPENSE_DESCRIPTION_MATCH_MIN_SCORE) {
            return null;
        }

        $normalized = $this->treeResolver->normalizeToTreeIds($bestParentId, $bestChildId, 'expense');
        if ($normalized === null) {
            return null;
        }

        $normalized['fallback'] = false;

        return $normalized;
    }

    /**
     * Prefer the best sibling under the resolved parent when the wording matches another child much better than the current leaf (e.g. model returns Loans but picks Mortgage alphabetically).
     *
     * A swap means the wording (not the model) picked the final leaf, so it clears the fallback flag.
     *
     * @param  array{category_id: int, subcategory_id: int, fallback?: bool}  $normalized
     * @return array{category_id: int, subcategory_id: int, fallback: bool}
     */
    public function refineExpenseSiblingByDescription(string $description, array $normalized): array
    {
        $normalized['fallback'] = (bool) ($normalized['fallback'] ?? false);

        $parentId = (int) ($normalized['category_id'] ?? 0);
        $currentChildId = (int) ($normalized['subcategory_id'] ?? 0);
        if ($parentId <= 0 || $currentChildId <= 0) {
            return $normalized;
        }

        $parent = Category::query()
            ->active()
            ->parent()
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->whereKey($parentId)
            ->first();

        if (! $parent || $parent->activeChildren->isEmpty()) {
            return $normalized;
        }

        $scores = [];
        foreach ($parent->activeChildren as $child) {
            $scores[(int) $child->id] = $this->scoreExpenseDescriptionAgainstCandidate(
                $description,
                $parent->name,
                $child->name,
            );
        }

        arsort($scores);
        reset($scores);
        $bestChildId = (int) key($scores);
        $bestScore = reset($scores);
        $currentScore = $scores[$currentChildId] ?? PHP_INT_MIN;

        if ($bestChildId === $currentChildId) {
            return $normalized;
        }

        if ($bestScore < self::EXPENSE_DESCRIPTION_MATCH_MIN_SCORE) {
            return $normalized;
        }

        if (($bestScore - $currentScore) < self::EXPENSE_REFINE_MARGIN) {
            return $normalized;
        }

        $swapped = $this->treeResolver->normalizeToTreeIds($parentId, $bestChildId, 'expense') ?? $normalized;
        $swapped['fallback'] = false;

        return $swapped;
    }

    public function scoreExpenseDescriptionAgainstCandidate(string $description, string $parentName, string $childName): int
    {
        $d = strtolower($description);
        $label = strtolower($parentName).' '.strtolower($childName);

        $score = 0;

        if (preg_match('/credit\s*-?\s*card(?:\s+(?:payment|bill|due|principal|txn|transaction))?|\bcard\s+payment\b|\bcc\s+(?:payment|bill|due)/', $d)) {
            if (preg_match('/credit\s*-?\s*card|\bcc\b/', $label)) {
                $score += 72;
            }
        }

        if (preg_match('/\b(?:housing|home)\s*loan\b|\bmortgage\b/', $d) && preg_match('/mortgage|home\s*[- ]?\s*equity/', $label)) {
            $score += 72;
        }

        if (preg_match('/student(?:\s+loan)?/', $d) && preg_match('/\bstudent\b/', $label)) {
            $score += 72;
        }

        if (preg_match('/\b(auto|vehicle|car)\s+loan\b/', $d) && preg_match('/\bauto\b/', $label)) {
            $score += 60;
        }

        $normalizedDesc = strtolower(preg_replace('/[^a-z0-9]+/', ' ', $description) ?? '');

        foreach (preg_split('/\s+/u', trim($normalizedDesc)) as $word) {
            $word = trim($word);
            if (strlen($word) < 3) {
                continue;
            }

            // Skip noisy bank/card issuer tokens that rarely align with taxonomy names
            $noise = ['indusind', 'hdfc', 'icici', 'axis', 'sbi', 'via', 'neftrtgsimps', 'upi'];
            if (in_array($word, $noise, true)) {
                continue;
            }

            if (str_contains($label, $word)) {
                $score += 6;
            }
        }

        return $score;
    }
}
