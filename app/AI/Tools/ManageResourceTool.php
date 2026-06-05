<?php

namespace App\AI\Tools;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Prism\Prism\Tool;

class ManageResourceTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('manage_resource')
            ->for(
                'Create, update, or delete a budget, category, or transaction. ' .
                'action=create: creates the resource. ' .
                'action=update: patches a transaction (resource_id required). ' .
                'action=delete: removes a transaction or deactivates a budget (resource_id + confirmed required). ' .
                'For deletes, always call with confirmed=no first so the user can review, then confirmed=yes only after they say yes.'
            )
            ->withEnumParameter('action', 'What to do: create, update, or delete', ['create', 'update', 'delete'])
            ->withEnumParameter('resource', 'What to act on: budget, category, or transaction', ['budget', 'category', 'transaction'])
            ->withNumberParameter('resource_id', 'Required for update/delete: the numeric ID from a prior search result', false)
            ->withEnumParameter('confirmed', 'For delete: confirmed=no shows details for review; confirmed=yes executes the delete', ['yes', 'no'], false)
            // Shared fields — tool uses only what is relevant to the action+resource combination
            ->withStringParameter('name', 'Budget name or category name', false)
            ->withNumberParameter('amount', 'Budget limit (create budget) or new transaction amount (update transaction)', false)
            ->withStringParameter('category_name', 'Category name: required for create budget; optional for update transaction', false)
            ->withStringParameter('parent_category_name', 'Parent category name when creating a subcategory', false)
            ->withStringParameter('description', 'Transaction description (update) or category description (create)', false)
            ->withEnumParameter('period_type', 'Budget period type', ['monthly', 'yearly', 'custom'], false)
            ->withStringParameter('start_date', 'Budget start date YYYY-MM-DD (required for period_type=custom)', false)
            ->withStringParameter('end_date', 'Budget end date YYYY-MM-DD (required for period_type=custom)', false)
            ->withEnumParameter('transaction_type', 'New transaction type for update', ['income', 'expense'], false)
            ->withStringParameter('date', 'New transaction date YYYY-MM-DD for update', false)
            ->withStringParameter('notes', 'Budget notes (optional)', false)
            ->using($this->execute(...));
    }

    public function execute(
        string  $action,
        string  $resource,
        ?float  $resource_id        = null,
        ?string $confirmed          = null,
        ?string $name               = null,
        ?float  $amount             = null,
        ?string $category_name      = null,
        ?string $parent_category_name = null,
        ?string $description        = null,
        ?string $period_type        = null,
        ?string $start_date         = null,
        ?string $end_date           = null,
        ?string $transaction_type   = null,
        ?string $date               = null,
        ?string $notes              = null,
    ): string {
        try {
            return match (true) {
                $action === 'create' && $resource === 'budget'      => $this->createBudget($name, $amount, $category_name, $period_type, $start_date, $end_date, $notes),
                $action === 'create' && $resource === 'category'    => $this->createCategory($name, $parent_category_name, $description),
                $action === 'update' && $resource === 'transaction' => $this->updateTransaction((int) $resource_id, $amount, $description, $date, $category_name, $transaction_type),
                $action === 'delete' && $resource === 'transaction' => $this->deleteTransaction((int) $resource_id, $confirmed),
                $action === 'delete' && $resource === 'budget'      => $this->deleteBudget((int) $resource_id, $confirmed),
                default => json_encode([
                    'success' => false,
                    'error'   => "Unsupported combination: action={$action}, resource={$resource}.",
                ]),
            };
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'manage_resource failed: ' . $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Budget
    // -------------------------------------------------------------------------

    private function createBudget(
        ?string $name,
        ?float  $amount,
        ?string $categoryName,
        ?string $periodType,
        ?string $startDate,
        ?string $endDate,
        ?string $notes,
    ): string {
        if (!$amount || $amount <= 0) {
            return json_encode(['success' => false, 'error' => 'amount is required and must be > 0 to create a budget.']);
        }
        if (empty($categoryName)) {
            return json_encode(['success' => false, 'error' => 'category_name is required to create a budget.']);
        }

        $category = Category::where('name', 'like', '%' . $categoryName . '%')
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return json_encode(['success' => false, 'error' => "Category \"{$categoryName}\" not found. Use list_categories to see available options."]);
        }

        $period = $periodType ?? 'monthly';

        [$start, $end] = match ($period) {
            'yearly' => [
                Carbon::now()->startOfYear()->toDateString(),
                Carbon::now()->endOfYear()->toDateString(),
            ],
            'custom' => [
                $startDate ?? Carbon::today()->toDateString(),
                $endDate   ?? Carbon::today()->addMonthNoOverflow()->toDateString(),
            ],
            default => [ // monthly
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ],
        };

        $budget = Budget::create([
            'category_id' => $category->id,
            'name'        => $name ?: $category->name . ' Budget',
            'amount'      => $amount,
            'period_type' => $period,
            'start_date'  => $start,
            'end_date'    => $end,
            'is_active'   => true,
            'notes'       => $notes,
        ]);

        return json_encode([
            'success'     => true,
            'id'          => $budget->id,
            'message'     => "Budget \"{$budget->name}\" created: ₹{$amount} for {$category->name} ({$period}, {$start} → {$end}).",
            'category'    => $category->name,
            'period_type' => $period,
            'start_date'  => $start,
            'end_date'    => $end,
        ]);
    }

    private function deleteBudget(int $id, ?string $confirmed): string
    {
        $budget = Budget::with('category')->find($id);
        if (!$budget) {
            return json_encode(['success' => false, 'error' => "Budget ID {$id} not found."]);
        }

        if ($confirmed !== 'yes') {
            return json_encode([
                'success'  => false,
                'pending_confirmation' => true,
                'message'  => "Please confirm: deactivate budget \"{$budget->name}\" (₹{$budget->amount} for {$budget->category?->name})? Reply yes to confirm.",
                'budget'   => [
                    'id'       => $budget->id,
                    'name'     => $budget->name,
                    'category' => $budget->category?->name,
                    'amount'   => (float) $budget->amount,
                ],
            ]);
        }

        $budget->update(['is_active' => false]);

        return json_encode([
            'success' => true,
            'message' => "Budget \"{$budget->name}\" has been deactivated.",
        ]);
    }

    // -------------------------------------------------------------------------
    // Category
    // -------------------------------------------------------------------------

    private function createCategory(?string $name, ?string $parentName, ?string $description): string
    {
        if (empty($name)) {
            return json_encode(['success' => false, 'error' => 'name is required to create a category.']);
        }

        // Duplicate check
        $existing = Category::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing) {
            return json_encode([
                'success' => false,
                'error'   => "Category \"{$name}\" already exists (ID: {$existing->id}).",
            ]);
        }

        $parentId = null;
        if (!empty($parentName)) {
            $parent = Category::where('name', 'like', '%' . $parentName . '%')
                ->whereNull('parent_id')
                ->first();
            if (!$parent) {
                return json_encode(['success' => false, 'error' => "Parent category \"{$parentName}\" not found."]);
            }
            $parentId = $parent->id;
        }

        $category = Category::create([
            'name'        => $name,
            'parent_id'   => $parentId,
            'description' => $description,
            'is_active'   => true,
        ]);

        return json_encode([
            'success'  => true,
            'id'       => $category->id,
            'name'     => $category->name,
            'parent'   => $parentId ? Category::find($parentId)?->name : null,
            'message'  => "Category \"{$name}\" created successfully.",
        ]);
    }

    // -------------------------------------------------------------------------
    // Transaction
    // -------------------------------------------------------------------------

    private function updateTransaction(
        int     $id,
        ?float  $amount,
        ?string $description,
        ?string $date,
        ?string $categoryName,
        ?string $transactionType,
    ): string {
        if (!$id) {
            return json_encode(['success' => false, 'error' => 'resource_id is required to update a transaction.']);
        }

        $transaction = Transaction::find($id);
        if (!$transaction) {
            return json_encode(['success' => false, 'error' => "Transaction ID {$id} not found."]);
        }

        $updates = [];

        if ($amount !== null && $amount > 0) {
            $updates['amount'] = $amount;
        }
        if (!empty($description)) {
            $updates['description'] = $description;
        }
        if (!empty($date)) {
            try {
                $updates['transaction_date'] = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                return json_encode(['success' => false, 'error' => "Invalid date format: {$date}. Use YYYY-MM-DD."]);
            }
        }
        if (!empty($transactionType)) {
            $updates['transaction_type'] = $transactionType;
        }
        if (!empty($categoryName)) {
            $category = Category::where('name', 'like', '%' . $categoryName . '%')
                ->whereNotNull('parent_id')
                ->first();
            if ($category) {
                $updates['category_id'] = $category->id;
            }
        }

        if (empty($updates)) {
            return json_encode(['success' => false, 'error' => 'No fields to update were provided.']);
        }

        $transaction->update($updates);
        $transaction->refresh();

        return json_encode([
            'success'     => true,
            'message'     => "Transaction ID {$id} updated successfully.",
            'transaction' => [
                'id'          => $transaction->id,
                'date'        => $transaction->transaction_date->format('Y-m-d'),
                'description' => $transaction->description,
                'amount'      => (float) $transaction->amount,
                'type'        => $transaction->transaction_type,
                'category'    => $transaction->category?->name,
                'account'     => $transaction->account?->name,
            ],
        ]);
    }

    private function deleteTransaction(int $id, ?string $confirmed): string
    {
        if (!$id) {
            return json_encode(['success' => false, 'error' => 'resource_id is required to delete a transaction.']);
        }

        $transaction = Transaction::with(['category', 'account'])->find($id);
        if (!$transaction) {
            return json_encode(['success' => false, 'error' => "Transaction ID {$id} not found."]);
        }

        if ($confirmed !== 'yes') {
            return json_encode([
                'success'              => false,
                'pending_confirmation' => true,
                'message'              => "Please confirm deletion of: ₹{$transaction->amount} — \"{$transaction->description}\" on {$transaction->transaction_date->format('Y-m-d')}. Reply yes to confirm.",
                'transaction'          => [
                    'id'          => $transaction->id,
                    'date'        => $transaction->transaction_date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'amount'      => (float) $transaction->amount,
                    'type'        => $transaction->transaction_type,
                    'category'    => $transaction->category?->name,
                    'account'     => $transaction->account?->name,
                ],
            ]);
        }

        $transaction->delete(); // booted() hook auto-adjusts account balance

        return json_encode([
            'success' => true,
            'message' => "Transaction ID {$id} deleted. Account balance updated automatically.",
        ]);
    }
}
