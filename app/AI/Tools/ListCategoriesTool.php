<?php

namespace App\AI\Tools;

use App\Models\Category;
use Prism\Prism\Tool;

class ListCategoriesTool extends Tool
{
public function __construct()
    {
        $this
            ->as('list_categories')
            ->for('Fetch active categories filtered by type. Use type="income" for INCOME tree, type="expense" for expense parents+children, omit type for all. Returns hierarchical tree: [{id, name, children: [{id, name, transaction_count}, ...]}, ...] with transaction counts.')
            ->withEnumParameter('type', 'Optional: "income" (INCOME root+children), "expense" (non-income/transfer parents+children), omit for all.', ['income', 'expense'], false)
            ->using($this->execute(...));
    }

public function execute(?string $type = null): string
    {
        try {
            $query = Category::with(['parent', 'activeChildren' => fn($q) => $q->active()->withCount('transactions')])
                ->active()
                ->parent();

            if ($type === 'income') {
                $query->where('code', 'INCOME');
            } elseif ($type === 'expense') {
                $query->whereNotIn('code', ['INCOME', 'ACCOUNTTR']);
            }

            $parents = $query->orderBy('name')->get()
                ->filter(fn (Category $c) => $c->activeChildren->isNotEmpty());

            $tree = $parents->map(fn (Category $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'transaction_count' => $parent->transactions_count,
                'children' => $parent->activeChildren->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'transaction_count' => $child->transactions_count,
                ])->values()->all(),
            ])->values()->all();

            return json_encode([
                'success' => true,
                'count' => count($tree),
                'tree_structure' => $tree,
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Failed to fetch categories: ' . $e->getMessage(),
            ]);
        }
    }
}
