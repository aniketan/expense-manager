<?php

namespace App\Services\AiCategorization;

use App\Models\Category;
use Illuminate\Support\Collection;
use Prism\Prism\ValueObjects\ToolResult;

class CategoryTreeResolver
{
    /**
     * Ensure parent + child ids match the transaction type. Uses first available for fallback.
     *
     * The `fallback` flag is true when the ids came from a default leaf (or from replacing
     * an invalid model choice with one), and false when they match valid model-proposed ids.
     *
     * @return array{category_id: int, subcategory_id: int, fallback: bool}|null
     */
    public function normalizeToTreeIds(?int $parentId, ?int $childId, string $transactionType): ?array
    {
        if ($transactionType === 'income') {
            return $this->normalizeIncomeTreeIds($parentId, $childId);
        }

        return $this->normalizeExpenseTreeIds($parentId, $childId);
    }

    /**
     * @return array{category_id: int, subcategory_id: int, fallback: bool}|null
     */
    public function normalizeIncomeTreeIds(?int $parentId, ?int $childId): ?array
    {
        $incomeRoot = Category::query()
            ->active()
            ->incomeRoot()
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
                    'fallback' => false,
                ];
            }
        }

        return [
            'category_id' => (int) $incomeRoot->id,
            'subcategory_id' => (int) $incomeRoot->activeChildren->first()->id,
            'fallback' => true,
        ];
    }

    /**
     * @return array{category_id: int, subcategory_id: int, fallback: bool}|null
     */
    public function normalizeExpenseTreeIds(?int $parentId, ?int $childId): ?array
    {
        $expenseParents = Category::query()
            ->active()
            ->expenseParent()
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
                if ($parentOfChild && $this->isNonExpenseRoot($parentOfChild)) {
                    return $this->fallbackExpenseLeaf($expenseParents);
                }

                $parent = $expenseParents->get($child->parent_id);
                if ($parent) {
                    return [
                        'category_id' => (int) $parent->id,
                        'subcategory_id' => (int) $child->id,
                        'fallback' => false,
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
                    'fallback' => true,
                ];
            }
        }

        return $this->fallbackExpenseLeaf($expenseParents);
    }

    /**
     * @param  Collection<int, Category>  $expenseParents
     * @return array{category_id: int, subcategory_id: int, fallback: bool}|null
     */
    public function fallbackExpenseLeaf(Collection $expenseParents): ?array
    {
        $other = Category::query()
            ->active()
            ->expenseParent()
            ->where('name', 'Other')
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->first();

        if ($other && $other->activeChildren->isNotEmpty()) {
            return [
                'category_id' => (int) $other->id,
                'subcategory_id' => (int) $other->activeChildren->first()->id,
                'fallback' => true,
            ];
        }

        $firstParent = $expenseParents->first();
        if ($firstParent && $firstParent->activeChildren->isNotEmpty()) {
            return [
                'category_id' => (int) $firstParent->id,
                'subcategory_id' => (int) $firstParent->activeChildren->first()->id,
                'fallback' => true,
            ];
        }

        $anyChild = Category::query()
            ->active()
            ->whereNotNull('parent_id')
            ->whereHas('parent', fn ($q) => $q->expenseParent())
            ->orderBy('id')
            ->first();

        if ($anyChild && $anyChild->parent_id) {
            return [
                'category_id' => (int) $anyChild->parent_id,
                'subcategory_id' => (int) $anyChild->id,
                'fallback' => true,
            ];
        }

        return null;
    }

    public function isNonExpenseRoot(Category $category): bool
    {
        $code = strtolower((string) $category->code);
        $name = strtolower((string) $category->name);

        return in_array($code, ['income', 'accounttr', 'account_transfer'], true)
            || in_array($name, ['income', 'account transfer'], true);
    }

    /**
     * @param  ToolResult[]  $toolResults
     * @return array<int, array<string, mixed>>|null
     */
    public function expenseTreeFromToolResults(array $toolResults): ?array
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
}
