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
            ->for('Fetch all active categories from the database. Call this FIRST whenever the user mentions a category name, so you can find the correct category IDs to pass to search_transactions. Returns id, name, parent category name, and transaction count for every active category.')
            ->using($this->execute(...));
    }

    public function execute(): string
    {
        try {
            $categories = Category::with('parent')
                ->where('is_active', true)
                ->withCount('transactions')
                ->orderBy('name')
                ->get();

            $result = $categories->map(fn (Category $cat) => [
                'id'                => $cat->id,
                'name'              => $cat->name,
                'parent'            => $cat->parent?->name,
                'transaction_count' => $cat->transactions_count,
            ])->values()->all();

            return json_encode([
                'success'    => true,
                'count'      => count($result),
                'categories' => $result,
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'Failed to fetch categories: ' . $e->getMessage(),
            ]);
        }
    }
}
