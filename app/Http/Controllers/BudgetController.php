<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class BudgetController extends Controller
{
    /**
     * Display a listing of budgets.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $query = Budget::with('category');

        // Filter by status
        if ($request->filled('status')) {
            if ($request->get('status') === 'active') {
                $query->active();
            } elseif ($request->get('status') === 'current') {
                $query->current();
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->get('category'));
        }

        // Filter by period type
        if ($request->filled('period_type')) {
            $query->where('period_type', $request->get('period_type'));
        }

        $budgets = $query->orderBy('start_date', 'desc')
                        ->paginate($perPage)
                        ->appends($request->query());

        // Add computed attributes to each budget
        $budgets->getCollection()->transform(function ($budget) {
            $budget->spent_amount = $budget->spent_amount;
            $budget->remaining_amount = $budget->remaining_amount;
            $budget->percentage_used = $budget->percentage_used;
            $budget->status = $budget->status;
            return $budget;
        });

        $categories = Category::active()->get();

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'categories' => $categories,
            'filters' => $request->only(['status', 'category', 'period_type']),
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * Show the form for creating a new budget.
     */
    public function create()
    {
        $categories = Category::active()->get();

        return Inertia::render('Budgets/Create', [
            'categories' => $categories,
            'periodTypes' => Budget::getPeriodTypes(),
        ]);
    }

    /**
     * Store a newly created budget in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'period_type' => 'required|in:monthly,yearly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        // Check for overlapping budgets for the same category
        $overlapping = Budget::where('category_id', $validated['category_id'])
            ->where('is_active', true)
            ->where(function($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhere(function($q) use ($validated) {
                          $q->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                      });
            })
            ->exists();

        if ($overlapping) {
            return Redirect::back()
                ->withErrors(['category_id' => 'A budget already exists for this category in the selected date range.'])
                ->withInput();
        }

        Budget::create($validated);

        return Redirect::route('budgets.index')
            ->with('success', 'Budget created successfully.');
    }

    /**
     * Display the specified budget.
     */
    public function show(Budget $budget)
    {
        $budget->load('category');
        $budget->spent_amount = $budget->spent_amount;
        $budget->remaining_amount = $budget->remaining_amount;
        $budget->percentage_used = $budget->percentage_used;
        $budget->status = $budget->status;

        // Get transactions for this budget period
        $transactions = $budget->category->transactions()
            ->whereBetween('transaction_date', [$budget->start_date, $budget->end_date])
            ->where('transaction_type', 'expense')
            ->with('account')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return Inertia::render('Budgets/Show', [
            'budget' => $budget,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Show the form for editing the specified budget.
     */
    public function edit(Budget $budget)
    {
        $categories = Category::active()->get();

        return Inertia::render('Budgets/Edit', [
            'budget' => $budget,
            'categories' => $categories,
            'periodTypes' => Budget::getPeriodTypes(),
        ]);
    }

    /**
     * Update the specified budget in storage.
     */
    public function update(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'period_type' => 'required|in:monthly,yearly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        // Check for overlapping budgets (excluding current budget)
        $overlapping = Budget::where('category_id', $validated['category_id'])
            ->where('id', '!=', $budget->id)
            ->where('is_active', true)
            ->where(function($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhere(function($q) use ($validated) {
                          $q->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                      });
            })
            ->exists();

        if ($overlapping) {
            return Redirect::back()
                ->withErrors(['category_id' => 'A budget already exists for this category in the selected date range.'])
                ->withInput();
        }

        $budget->update($validated);

        return Redirect::route('budgets.index')
            ->with('success', 'Budget updated successfully.');
    }

    /**
     * Remove the specified budget from storage.
     */
    public function destroy(Budget $budget)
    {
        $budget->delete();

        return Redirect::route('budgets.index')
            ->with('success', 'Budget deleted successfully.');
    }

    /**
     * Toggle budget status (active/inactive).
     */
    public function toggleStatus(Budget $budget)
    {
        $budget->update(['is_active' => !$budget->is_active]);

        $status = $budget->is_active ? 'activated' : 'deactivated';

        return Redirect::back()
            ->with('success', "Budget {$status} successfully.");
    }

    /**
     * Get budget summary for dashboard.
     */
    public function getSummary()
    {
        $currentBudgets = Budget::with('category')
            ->active()
            ->current()
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'category' => $budget->category->name,
                    'amount' => $budget->amount,
                    'spent_amount' => $budget->spent_amount,
                    'remaining_amount' => $budget->remaining_amount,
                    'percentage_used' => $budget->percentage_used,
                    'status' => $budget->status,
                ];
            });

        return response()->json($currentBudgets);
    }
}
