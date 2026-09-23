<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'description',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Category types as constants (you can add more as needed)
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_BOTH = 'both';

    // Get all available category types
    public static function getTypes(): array
    {
        return [
            self::TYPE_INCOME => 'Income',
            self::TYPE_EXPENSE => 'Expense',
            self::TYPE_BOTH => 'Both',
        ];
    }

    // Scope for active categories
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for parent categories (top-level)
    public function scopeParent($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope for child categories (sub-categories)
    public function scopeChild($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeIncomeRoot($query)
    {
        return $query->parent()
            ->where(function ($q) {
                $q->whereRaw('LOWER(COALESCE(code, ?)) = ?', ['', 'income'])
                    ->orWhereRaw('LOWER(name) = ?', ['income']);
            });
    }

    public function scopeExpenseParent($query)
    {
        return $query->parent()
            ->whereNot(function ($q) {
                $q->whereRaw('LOWER(COALESCE(code, ?)) IN (?, ?, ?)', ['', 'income', 'accounttr', 'account_transfer'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['income', 'account transfer']);
            });
    }

    /**
     * Active leaf categories a regular income or expense transaction may be filed under.
     * Excludes the account-transfer tree, whose categories are reserved for transfer legs.
     */
    public function scopeAssignableFor($query, string $transactionType)
    {
        return $query->active()
            ->child()
            ->whereHas('parent', fn ($q) => $transactionType === Transaction::TYPE_INCOME
                ? $q->incomeRoot()
                : $q->expenseParent());
    }

    public function isTransferCategory(): bool
    {
        return in_array($this->code, Transaction::TRANSFER_CATEGORY_CODES, true);
    }

    public static function isTransferCategoryId(mixed $categoryId): bool
    {
        return $categoryId !== null
            && static::query()->whereKey($categoryId)->whereIn('code', Transaction::TRANSFER_CATEGORY_CODES)->exists();
    }

    // Relationship: Parent category
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relationship: Child categories
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relationship: Active child categories
    public function activeChildren()
    {
        return $this->hasMany(Category::class, 'parent_id')->where('is_active', true);
    }

    // Relationship: Transactions belonging to this category
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Check if category has children
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    // Check if category is parent/top-level
    public function isParent()
    {
        return is_null($this->parent_id);
    }
}
