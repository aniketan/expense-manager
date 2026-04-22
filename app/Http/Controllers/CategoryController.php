<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 5); // Changed to 5 parent categories per page
        $page = $request->get('page', 1);
        
        // Validate per_page parameter
        if (!in_array($perPage, [5, 10, 15, 20])) {
            $perPage = 5;
        }

        // Get all categories to properly group them
        $allCategories = Category::with(['parent', 'children'])
            ->orderBy('name')
            ->get();

        // Load transaction counts separately for efficiency
        $categoriesWithTransactionTotals = $allCategories->map(function($category) {
            // Calculate totals
            if ($category->isParent()) {
                // For parent categories, get sum of all children + parent
                $childrenIds = $category->children->pluck('id')->toArray();
                $allIds = array_merge($childrenIds, [$category->id]);
                
                // Set the main total properties (sum of all subcategories)
                $totalAmount = \App\Models\Transaction::whereIn('category_id', $allIds)->sum('amount');
                $transactionCount = \App\Models\Transaction::whereIn('category_id', $allIds)->count();
                
                $category->total_amount = $totalAmount;
                $category->transactions_count = $transactionCount;
                
                // Also set the _with_children properties for API compatibility
                $category->total_amount_with_children = $totalAmount;
                $category->transaction_count_with_children = $transactionCount;
            } else {
                // For children, just get their individual totals
                $totalAmount = \App\Models\Transaction::where('category_id', $category->id)->sum('amount');
                $transactionCount = \App\Models\Transaction::where('category_id', $category->id)->count();
                
                $category->total_amount = $totalAmount;
                $category->transactions_count = $transactionCount;
            }
            
            return $category;
        });

        // Group into parent categories with their children
        $parentCategories = $categoriesWithTransactionTotals->filter(function($category) {
            return $category->parent_id === null;
        });

        // Add children to each parent
        $groupedCategories = $parentCategories->map(function($parent) use ($categoriesWithTransactionTotals) {
            $parent->children = $categoriesWithTransactionTotals->filter(function($category) use ($parent) {
                return $category->parent_id === $parent->id;
            })->values();
            return $parent;
        });

        // Manual pagination for grouped categories
        $total = $groupedCategories->count();
        $offset = ($page - 1) * $perPage;
        $paginatedGroups = $groupedCategories->slice($offset, $perPage)->values();

        // Flatten the groups for frontend display
        $flattenedCategories = collect();
        foreach ($paginatedGroups as $parent) {
            $flattenedCategories->push($parent);
            foreach ($parent->children as $child) {
                $flattenedCategories->push($child);
            }
        }

        // Create pagination info manually
        $paginationData = [
            'data' => $flattenedCategories,
            'current_page' => (int) $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => (int) $perPage,
            'total' => $categoriesWithTransactionTotals->count(), // Total individual categories
            'from' => $offset + 1,
            'to' => min($offset + $flattenedCategories->count(), $categoriesWithTransactionTotals->count()),
            'parent_total' => $total, // Total parent categories
        ];

        return Inertia::render('Categories/Index', [
            'categories' => $paginationData,
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        $parentCategories = Category::active()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Create', [
            'categoryTypes' => Category::getTypes(),
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:categories',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'sometimes|boolean',
        ]);

        // Set defaults
        $validated['color'] = $validated['color'] ?? '#3B82F6';
        $validated['is_active'] = $validated['is_active'] ?? true;

        Category::create($validated);

        return Redirect::route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        // Load relationships
        $category->load(['parent', 'children']);
        
        // Get transactions for this category
        $transactions = $category->transactions()
            ->with(['account'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Calculate statistics
        if ($category->parent_id === null) {
            // For parent categories, include children's transactions
            $childrenIds = $category->children->pluck('id')->toArray();
            $allIds = array_merge($childrenIds, [$category->id]);
            
            $totalAmount = \App\Models\Transaction::whereIn('category_id', $allIds)->sum('amount');
            $transactionCount = \App\Models\Transaction::whereIn('category_id', $allIds)->count();
            
            // Get breakdown by child
            $childrenStats = $category->children->map(function($child) {
                $childAmount = \App\Models\Transaction::where('category_id', $child->id)->sum('amount');
                $childCount = \App\Models\Transaction::where('category_id', $child->id)->count();
                
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'icon' => $child->icon,
                    'color' => $child->color,
                    'total_amount' => $childAmount,
                    'transaction_count' => $childCount,
                ];
            });
        } else {
            // For child categories
            $totalAmount = \App\Models\Transaction::where('category_id', $category->id)->sum('amount');
            $transactionCount = \App\Models\Transaction::where('category_id', $category->id)->count();
            $childrenStats = collect([]);
        }
        
        return Inertia::render('Categories/Show', [
            'category' => $category,
            'transactions' => $transactions,
            'stats' => [
                'total_amount' => $totalAmount,
                'transaction_count' => $transactionCount,
                'children_stats' => $childrenStats,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category)
    {
        $parentCategories = Category::active()
            ->whereNull('parent_id')
            ->where('id', '!=', $category->id) // Exclude self from parent options
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Edit', [
            'category' => $category->load('parent'),
            'categoryTypes' => Category::getTypes(),
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'sometimes|boolean',
        ]);

        // Prevent setting self as parent
        if ($validated['parent_id'] == $category->id) {
            return Redirect::back()
                ->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        $category->update($validated);

        return Redirect::route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        try {
            // Check if category has children
            if ($category->hasChildren()) {
                return Redirect::route('categories.index')
                    ->with('error', 'Cannot delete category. It has sub-categories.');
            }

            $category->delete();
            
            return Redirect::route('categories.index')
                ->with('success', 'Category deleted successfully.');
        } catch (\Exception $e) {
            return Redirect::route('categories.index')
                ->with('error', 'Cannot delete category. It may be associated with transactions.');
        }
    }

    /**
     * Toggle category status (active/inactive).
     */
    public function toggleStatus(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        
        $status = $category->is_active ? 'activated' : 'deactivated';
        
        return Redirect::route('categories.index')
            ->with('success', "Category {$status} successfully.");
    }

    /**
     * Get categories for API/AJAX requests.
     */
    public function getCategories()
    {
        return response()->json([
            'categories' => Category::active()->orderBy('name')->get()
        ]);
    }

    /**
     * Get parent categories for API/AJAX requests.
     */
    public function getParentCategories()
    {
        return response()->json([
            'categories' => Category::active()->parent()->orderBy('name')->get()
        ]);
    }

    /**
     * Get child categories for a specific parent.
     */
    public function getChildCategories(Category $category)
    {
        return response()->json([
            'categories' => $category->activeChildren()->orderBy('name')->get()
        ]);
    }

    /**
     * Get categories with transaction totals for dashboard/reports
     */
    public function getCategoriesWithTotals()
    {
        $categories = Category::with(['children'])
            ->whereNull('parent_id') // Only parent categories
            ->orderBy('name')
            ->get();

        $categoriesWithTotals = $categories->map(function ($category) {
            // Calculate totals for parent category
            $childrenIds = $category->children->pluck('id')->toArray();
            $allIds = array_merge($childrenIds, [$category->id]);
            
            $totalAmount = \App\Models\Transaction::whereIn('category_id', $allIds)->sum('amount');
            $transactionCount = \App\Models\Transaction::whereIn('category_id', $allIds)->count();
            
            return [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'color' => $category->color,
                'icon' => $category->icon,
                'total_amount' => $totalAmount,
                'transaction_count' => $transactionCount,
                'children' => $category->children->map(function ($child) {
                    $childAmount = \App\Models\Transaction::where('category_id', $child->id)->sum('amount');
                    $childCount = \App\Models\Transaction::where('category_id', $child->id)->count();
                    
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'code' => $child->code,
                        'total_amount' => $childAmount,
                        'transaction_count' => $childCount,
                    ];
                })
            ];
        });

        return response()->json([
            'categories' => $categoriesWithTotals
        ]);
    }
}
