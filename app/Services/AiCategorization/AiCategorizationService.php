<?php

namespace App\Services\AiCategorization;

use App\AI\Tools\ListCategoriesTool;
use App\Models\Category;
use App\Services\LlmLoggingService;
use Prism\Prism\Facades\Prism;

class AiCategorizationService
{
    public const FALLBACK_REASON = 'AI could not determine a category; showing a default suggestion for review.';

    public function __construct(
        private CategoryTreeResolver $treeResolver,
        private CategoryMatcher $categoryMatcher,
        private LlmLoggingService $logger,
    ) {}

    /**
     * Categorize a transaction description via the LLM, normalizing the result to the category tree.
     *
     * @return array{category_id: int, subcategory_id: int, confidence: string, reason: string, fallback: bool}
     *
     * @throws AiCategorizationException When no categories exist or the ids cannot be normalized (HTTP 422).
     */
    public function categorize(string $description, string $type): array
    {
        $logger = $this->logger;
        $sessionId = 'cat_'.uniqid();

        $provider = config('ai.provider');
        $model = config('ai.categorize_model') ?: config('ai.model');

        $logger->logRequest($sessionId, $provider, $model, ['description' => $description, 'type' => $type]);

        $maxTokens = config('ai.categorize_max_tokens', 512);
        $categorizeMaxSteps = (int) config('ai.categorize_max_steps', 4);

        // Check if categories exist for this type (quick DB check)
        $hasCategories = Category::active()->parent()
            ->when($type === 'income', fn ($q) => $q->incomeRoot())
            ->when($type === 'expense', fn ($q) => $q->expenseParent())
            ->exists();

        if (! $hasCategories) {
            throw new AiCategorizationException($type === 'income' ? 'No income categories configured' : 'No expense categories configured');
        }

        $userPrompt = $this->buildCategorizePrompt($description, $type);
        $logger->logMessages($sessionId, [], "Tool-enabled categorize: type={$type}");

        $start = microtime(true);
        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt('You are a financial categorization expert. You MUST call the list_categories tool with type='.$type.' first, read tree_structure names and ids, then output valid JSON only (no markdown) with matched category_id and subcategory_id from that tree.')
            ->withPrompt($userPrompt)
            ->withTools([new ListCategoriesTool])
            ->withMaxSteps(max(2, $categorizeMaxSteps))
            ->withMaxTokens($maxTokens)
            ->asText();

        $finalText = $response->text;

        $duration = round((microtime(true) - $start) * 1000);
        $logger->logResponse($sessionId, $duration, ['raw_text_length' => strlen($finalText)]);

        $logger->logMessages($sessionId, [], 'Raw LLM response: '.substr($finalText, 0, 1000));

        $data = $this->decodeJsonResponse($finalText);

        $logger->logMessages($sessionId, [], 'Parsed data: '.json_encode($data));

        $normalized = null;
        $matchedDescriptionToTree = false;

        $modelProposedIds = is_array($data) && (isset($data['category_id']) || isset($data['subcategory_id']));

        if ($modelProposedIds) {
            $normalized = $this->treeResolver->normalizeToTreeIds(
                isset($data['category_id']) ? (int) $data['category_id'] : null,
                isset($data['subcategory_id']) ? (int) $data['subcategory_id'] : null,
                $type,
            );
        } elseif ($type === 'expense') {
            $tree = $this->treeResolver->expenseTreeFromToolResults($response->toolResults);
            if (! $tree) {
                $fromToolJson = json_decode((new ListCategoriesTool)->execute('expense'), true);
                $tree = is_array($fromToolJson) ? ($fromToolJson['tree_structure'] ?? null) : null;
            }
            if (is_array($tree)) {
                $fromHeuristic = $this->categoryMatcher->resolveExpenseCategoryFromDescription($description, $tree);
                if ($fromHeuristic) {
                    $normalized = $fromHeuristic;
                    $matchedDescriptionToTree = true;
                    $logger->logMessages($sessionId, [], 'Description-to-tree heuristic: '.json_encode($normalized));
                }
            }
        }

        if (! $normalized) {
            $normalized = $this->treeResolver->normalizeToTreeIds(null, null, $type);
        }

        if ($type === 'expense') {
            $normalized = $this->categoryMatcher->refineExpenseSiblingByDescription($description, $normalized);
        }

        $logger->logMessages($sessionId, [], 'Normalized: '.json_encode($normalized));

        if (! $normalized) {
            $logger->logError($sessionId, 'normalize_failed', new \Exception('Invalid category IDs'));

            throw new AiCategorizationException('AI returned invalid category ids');
        }

        $isFallback = (bool) ($normalized['fallback'] ?? false);

        if ($matchedDescriptionToTree) {
            $successPayload = [
                'category_id' => $normalized['category_id'],
                'subcategory_id' => $normalized['subcategory_id'],
                'confidence' => 'medium',
                'reason' => 'Matched transaction wording to configured categories because the model output was missing or invalid.',
                'fallback' => false,
            ];
        } elseif (is_array($data)) {
            if ($isFallback) {
                $successPayload = [
                    'category_id' => $normalized['category_id'],
                    'subcategory_id' => $normalized['subcategory_id'],
                    'confidence' => 'low',
                    'reason' => self::FALLBACK_REASON,
                    'fallback' => true,
                ];
            } else {
                $successPayload = [
                    'category_id' => $normalized['category_id'],
                    'subcategory_id' => $normalized['subcategory_id'],
                    'confidence' => in_array($data['confidence'] ?? '', ['high', 'medium', 'low'], true)
                        ? $data['confidence']
                        : 'low',
                    'reason' => is_string($data['reason'] ?? null) ? $data['reason'] : 'Semantic categorization with fallback normalization',
                    'fallback' => false,
                ];
            }
        } else {
            $successPayload = [
                'category_id' => $normalized['category_id'],
                'subcategory_id' => $normalized['subcategory_id'],
                'confidence' => 'low',
                'reason' => $isFallback ? self::FALLBACK_REASON : 'Semantic categorization with fallback normalization',
                'fallback' => $isFallback,
            ];
        }

        $logger->logMessages($sessionId, [], 'SUCCESS: '.json_encode($successPayload));

        return $successPayload;
    }

    private function buildCategorizePrompt(string $description, string $type): string
    {
        $typeLabel = $type === 'income' ? 'income' : 'expense';

        return <<<PROMPT
Categorize this {$typeLabel} transaction by predicting the BEST MATCHING category names semantically (NOT ids), then call list_categories(type="{$type}"), then MATCH your predictions to the exact names/IDs in the tree_structure response.

Description: "{$description}"
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

    private function decodeJsonResponse(string $raw): ?array
    {
        $json = trim($raw);
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
        $json = preg_replace('/\s*```$/i', '', $json) ?? $json;

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
