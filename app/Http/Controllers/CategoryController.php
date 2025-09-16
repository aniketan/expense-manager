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
    public function index()
    {
        $categories = Category::with('parent', 'children')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
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
        return Inertia::render('Categories/Show', [
            'category' => $category->load(['parent', 'children']),
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
}
