<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Prism;
use App\Services\LlmLoggingService;
use App\AI\Tools\ListCategoriesTool;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\ValueObjects\ToolResult;

class AiController extends Controller
{
    private const EXPENSE_DESCRIPTION_MATCH_MIN_SCORE = 32;

    /** Margin required to swap to a sibling (model/parent-first picked wrong leaf). */
    private const EXPENSE_REFINE_MARGIN = 24;

    public function categorize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'type' => 'required|in:income,expense',
        ]);

        $logger = app(LlmLoggingService::class);
        $sessionId = 'cat_' . uniqid();

        $provider = config('ai.provider');
        $model = config('ai.categorize_model') ?: config('ai.model');

        $logger->logRequest($sessionId, $provider, $model, $validated);

        $type = $validated['type'];
        $maxTokens = config('ai.categorize_max_tokens', 512);
        $categorizeMaxSteps = (int) config('ai.categorize_max_steps', 4);

        // Check if categories exist for this type (quick DB check)
        $hasCategories = Category::active()->parent()
            ->when($type === 'income', fn($q) => $q->where('code', 'INCOME'))
            ->when($type === 'expense', fn($q) => $q->whereNotIn('code', ['INCOME', 'ACCOUNTTR']))
            ->exists();

        if (! $hasCategories) {
            $message = $type === 'income' ? 'No income categories configured' : 'No expense categories configured';
            return response()->json(['error' => $message], 422);
        }
        $userPrompt = $this->buildCategorizePrompt($validated['description'], $type);
        $logger->logMessages($sessionId, [], "Tool-enabled categorize: type={$type}"); 

        try {
            $start = microtime(true);
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt('You are a financial categorization expert. You MUST call the list_categories tool with type='.$type.' first, read tree_structure names and ids, then output valid JSON only (no markdown) with matched category_id and subcategory_id from that tree.')
                ->withPrompt($userPrompt)
                ->withTools([new ListCategoriesTool()])
                ->withMaxSteps(max(2, $categorizeMaxSteps))
                ->withMaxTokens($maxTokens)
                ->asText();

            $finalText = $response->text;

            $duration = round((microtime(true) - $start) * 1000);
            $logger->logResponse($sessionId, $duration, ['raw_text_length' => strlen($finalText)]);

            $logger->logMessages($sessionId, [], 'Raw LLM response: ' . substr($finalText, 0, 1000));

            $data = $this->decodeJsonResponse($finalText);

            $logger->logMessages($sessionId, [], 'Parsed data: ' . json_encode($data));

            $normalized = null;
            $matchedDescriptionToTree = false;

            $modelProposedIds = is_array($data) && (isset($data['category_id']) || isset($data['subcategory_id']));

            if ($modelProposedIds) {
                $normalized = $this->normalizeToTreeIds(
                    isset($data['category_id']) ? (int) $data['category_id'] : null,
                    isset($data['subcategory_id']) ? (int) $data['subcategory_id'] : null,
                    $type,
                );
            } elseif ($type === 'expense') {
                $tree = $this->expenseTreeFromToolResults($response->toolResults);
                if (! $tree) {
                    $fromToolJson = json_decode((new ListCategoriesTool)->execute('expense'), true);
                    $tree = is_array($fromToolJson) ? ($fromToolJson['tree_structure'] ?? null) : null;
                }
                if (is_array($tree)) {
                    $fromHeuristic = $this->resolveExpenseCategoryFromDescription($validated['description'], $tree);
                    if ($fromHeuristic) {
                        $normalized = $fromHeuristic;
                        $matchedDescriptionToTree = true;
                        $logger->logMessages($sessionId, [], 'Description-to-tree heuristic: ' . json_encode($normalized));
                    }
                }
            }

            if (! $normalized) {
                $normalized = $this->normalizeToTreeIds(null, null, $type);
            }

            if ($type === 'expense') {
                $normalized = $this->refineExpenseSiblingByDescription($validated['description'], $normalized);
            }

            $logger->logMessages($sessionId, [], 'Normalized: ' . json_encode($normalized));

            if (! $normalized) {
                $logger->logError($sessionId, 'normalize_failed', new \Exception('Invalid category IDs'));
                return response()->json(['error' => 'AI returned invalid category ids'], 422);
            }

            if ($matchedDescriptionToTree) {
                $successPayload = [
                    'category_id' => $normalized['category_id'],
                    'subcategory_id' => $normalized['subcategory_id'],
                    'confidence' => 'medium',
                    'reason' => 'Matched transaction wording to configured categories because the model output was missing or invalid.',
                ];
            } elseif (is_array($data)) {
                $successPayload = [
                    'category_id' => $normalized['category_id'],
                    'subcategory_id' => $normalized['subcategory_id'],
                    'confidence' => in_array($data['confidence'] ?? '', ['high', 'medium', 'low'], true)
                        ? $data['confidence']
                        : 'low',
                    'reason' => is_string($data['reason'] ?? null) ? $data['reason'] : 'Semantic categorization with fallback normalization',
                ];
            } else {
                $successPayload = [
                    'category_id' => $normalized['category_id'],
                    'subcategory_id' => $normalized['subcategory_id'],
                    'confidence' => 'low',
                    'reason' => 'Semantic categorization with fallback normalization',
                ];
            }

            $logger->logMessages($sessionId, [], 'SUCCESS: ' . json_encode($successPayload));

            return response()->json($successPayload);

        } catch (\Throwable $e) {
            $logger->logError($sessionId, 'prism_failed', $e);
            return response()->json(['error' => 'AI categorization failed: ' . $e->getMessage()], 503);
        }
    }

    private function buildCategorizePrompt(string $description, string $type): string
    {
        $typeLabel = $type === 'income' ? 'income' : 'expense';
        return <<<PROMPT
Categorize this {$typeLabel} transaction by predicting the BEST MATCHING category names semantically (NOT ids), then call list_categories(type="{$type}"), then MATCH your predictions to the exact names/IDs in the tree_structure response.

Description: \"{$description}\"
Type: {$typeLabel}

IMPORTANT WORKFLOW:
1. Predict parent category name (e.g. "Groceries", "Salary", "Fuel")
2. Predict child category name if applicable (e.g. "Monthly Grocery", "Salary")
3. Call: list_categories(type="{$type}")
4. From tree_structure, find EXACT or CLOSEST name matches to your predictions
5. Output ONLY this JSON with matched ids (confidence based on match quality):

{
  "category_id": matched_parent_id,
  "subcategory_id": matched_child_id, 
  "confidence": "high|medium|low",
  "reason": "brief match explanation"
}

If no good match, use first available tree item. Never invent IDs.
PROMPT;
    }

    /**
     * Process categorize events from tool streaming, extract category tool results, 
     * semantically match LLM predictions to DB categories, normalize tree IDs.
     * 
     * @return array{category_id: int, subcategory_id: int, confidence: string}|null
     */
    private function handleCategorizeEvents(array $events, string $type): ?array
    {
        $toolResult = null;
        $finalText = '';

        foreach ($events as $event) {
            if ($event instanceof ToolResultEvent && $event->toolCall->name === 'list_categories') {
                $toolResult = json_decode($event->content, true);
                if (!($toolResult['success'] ?? false)) {
                    return null;
                }
                break; // Assume single tool call per categorize
            } elseif ($event instanceof TextDeltaEvent) {
                $finalText .= $event->delta;
            }
        }

        if (! $toolResult || empty($toolResult['tree_structure'])) {
            return null;
        }

        $tree = $toolResult['tree_structure'];
        
        // Parse final text for JSON (LLM final output after tool)
        $finalJson = $this->decodeJsonResponse($finalText);
        if ($finalJson && isset($finalJson['category_id'], $finalJson['subcategory_id'])) {
            // LLM provided ids directly from matching
            return [
                'category_id' => (int) $finalJson['category_id'],
                'subcategory_id' => (int) $finalJson['subcategory_id'],
                'confidence' => $finalJson['confidence'] ?? 'medium',
            ];
        }

        // Fallback: simple first-match normalization using existing logic
        $categories = $this->fetchCategoriesByType($type);
        return $this->normalizeToTreeIds(null, null, $type); // Uses existing fallbacks
    }

    private function fetchCategoriesByType(string $type): array
    {
        // Reuse query logic from original buildCategoriesForPrompt
        if ($type === 'income') {
            $incomeRoot = Category::query()
                ->active()
                ->parent()
                ->where('code', 'INCOME')
                ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
                ->first();

            if (! $incomeRoot || $incomeRoot->activeChildren->isEmpty()) {
                return [];
            }

            return [[
                'id' => (int) $incomeRoot->id,
                'name' => $incomeRoot->name,
                'children' => $incomeRoot->activeChildren->map(fn (Category $ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                ])->values()->all(),
            ]];
        }

        return Category::query()
            ->active()
            ->parent()
            ->whereNotIn('code', ['INCOME', 'ACCOUNTTR'])
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $c) => $c->activeChildren->isNotEmpty())
            ->map(fn (Category $c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'children' => $c->activeChildren->map(fn (Category $ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  ToolResult[]  $toolResults
     * @return array<int, array<string, mixed>>|null
     */
    private function expenseTreeFromToolResults(array $toolResults): ?array
    {
        foreach ($toolResults as $tr) {
            if (! $tr instanceof ToolResult || ($tr->toolName ?? '') !== 'list_categories') {
                continue;
            }
            $raw = $tr->result;
            if (! is_string($raw)) {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && ! empty($decoded['tree_structure'])) {
                return $decoded['tree_structure'];
            }
        }

        return null;
    }

    /**
     * Pick best expense leaf by overlap between transaction text and category names (handles empty LLM output).
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array{category_id: int, subcategory_id: int}|null
     */
    private function resolveExpenseCategoryFromDescription(string $description, array $tree): ?array
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

        return $this->normalizeToTreeIds($bestParentId, $bestChildId, 'expense');
    }

    /**
     * Prefer the best sibling under the resolved parent when the wording matches another child much better than the current leaf (e.g. model returns Loans but picks Mortgage alphabetically).
     *
     * @param array{category_id: int, subcategory_id: int} $normalized
     * @return array{category_id: int, subcategory_id: int}
     */
    private function refineExpenseSiblingByDescription(string $description, array $normalized): array
    {
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

        return $this->normalizeToTreeIds($parentId, $bestChildId, 'expense') ?? $normalized;
    }

    private function scoreExpenseDescriptionAgainstCandidate(string $description, string $parentName, string $childName): int
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

    private function decodeJsonResponse(string $raw): ?array
    {
        $json = trim($raw);
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
        $json = preg_replace('/\s*```$/i', '', $json) ?? $json;

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Ensure parent + child ids match the transaction type. Uses first available for fallback.
     *
     * @return array{category_id: int, subcategory_id: int}|null
     */
    private function normalizeToTreeIds(?int $parentId, ?int $childId, string $transactionType): ?array
    {
        if ($transactionType === 'income') {
            return $this->normalizeIncomeTreeIds($parentId, $childId);
        }

        return $this->normalizeExpenseTreeIds($parentId, $childId);
    }

    /**
     * @return array{category_id: int, subcategory_id: int}|null
     */
    private function normalizeIncomeTreeIds(?int $parentId, ?int $childId): ?array
    {
        $incomeRoot = Category::query()
            ->active()
            ->parent()
            ->where('code', 'INCOME')
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->first();

        if (! $incomeRoot || $incomeRoot->activeChildren->isEmpty()) {
            return null;
        }

        if ($childId) {
            $child = Category::query()->active()->whereKey($childId)->first();
            if ($child && (int) $child->parent_id === (int) $incomeRoot->id) {
                return [
                    'category_id' => (int) $incomeRoot->id,
                    'subcategory_id' => (int) $child->id,
                ];
            }
        }

        return [
            'category_id' => (int) $incomeRoot->id,
            'subcategory_id' => (int) $incomeRoot->activeChildren->first()->id,
        ];
    }

    /**
     * @return array{category_id: int, subcategory_id: int}|null
     */
    private function normalizeExpenseTreeIds(?int $parentId, ?int $childId): ?array
    {
        $expenseParents = Category::query()
            ->active()
            ->parent()
            ->whereNotIn('code', ['INCOME', 'ACCOUNTTR'])
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->get()
            ->keyBy('id');

        if ($expenseParents->isEmpty()) {
            return null;
        }

        if ($childId) {
            $child = Category::query()->active()->whereKey($childId)->first();
            if ($child && $child->parent_id) {
                $parentOfChild = Category::query()->find($child->parent_id);
                if ($parentOfChild && $parentOfChild->code === 'INCOME') {
                    return $this->fallbackExpenseLeaf($expenseParents);
                }

                $parent = $expenseParents->get($child->parent_id);
                if ($parent) {
                    return [
                        'category_id' => (int) $parent->id,
                        'subcategory_id' => (int) $child->id,
                    ];
                }
            }
        }

        if ($parentId && $expenseParents->has($parentId)) {
            /** @var Category $parent */
            $parent = $expenseParents->get($parentId);
            $children = $parent->activeChildren;
            if ($children->isNotEmpty()) {
                return [
                    'category_id' => (int) $parent->id,
                    'subcategory_id' => (int) $children->first()->id,
                ];
            }
        }

        return $this->fallbackExpenseLeaf($expenseParents);
    }

    /**
     * @param  Collection<int, Category>  $expenseParents
     * @return array{category_id: int, subcategory_id: int}|null
     */
    private function fallbackExpenseLeaf(Collection $expenseParents): ?array
    {
        $other = Category::query()
            ->active()
            ->parent()
            ->where('name', 'Other')
            ->whereNotIn('code', ['INCOME', 'ACCOUNTTR'])
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->first();

        if ($other && $other->activeChildren->isNotEmpty()) {
            return [
                'category_id' => (int) $other->id,
                'subcategory_id' => (int) $other->activeChildren->first()->id,
            ];
        }

        $firstParent = $expenseParents->first();
        if ($firstParent && $firstParent->activeChildren->isNotEmpty()) {
            return [
                'category_id' => (int) $firstParent->id,
                'subcategory_id' => (int) $firstParent->activeChildren->first()->id,
            ];
        }

        $anyChild = Category::query()
            ->active()
            ->whereNotNull('parent_id')
            ->whereHas('parent', fn ($q) => $q->whereNull('parent_id')->whereNotIn('code', ['INCOME', 'ACCOUNTTR']))
            ->orderBy('id')
            ->first();

        if ($anyChild && $anyChild->parent_id) {
            return [
                'category_id' => (int) $anyChild->parent_id,
                'subcategory_id' => (int) $anyChild->id,
            ];
        }

        return null;
    }
}
