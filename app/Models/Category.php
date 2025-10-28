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

    // Get full category name (including parent if exists)
    public function getFullNameAttribute()
    {
        return $this->parent ? $this->parent->name . ' > ' . $this->name : $this->name;
    }

    // Get category hierarchy level
    public function getHierarchyLevelAttribute()
    {
        return $this->parent_id ? 1 : 0;
    }

    // Check if category is parent/top-level
    public function isParent()
    {
        return is_null($this->parent_id);
    }

    // Check if category is child/sub-category
    public function isChild()
    {
        return !is_null($this->parent_id);
    }

    // Calculate total transaction amount for this category only
    public function getTotalAmountAttribute()
    {
        return $this->transactions()->sum('amount');
    }

    // Calculate total transaction amount including subcategories (for parent categories)
    public function getTotalAmountWithChildrenAttribute()
    {
        if ($this->isParent()) {
            // For parent categories, sum all children's transaction amounts using efficient DB query
            $childrenIds = $this->children->pluck('id')->toArray();
            
            // If no children, just return parent's total
            if (empty($childrenIds)) {
                return $this->transactions()->sum('amount');
            }
            
            // Include parent ID as well (in case parent has direct transactions)
            $allIds = array_merge($childrenIds, [$this->id]);
            
            return Transaction::whereIn('category_id', $allIds)->sum('amount');
        }
        
        // For child categories, just return their own total
        return $this->total_amount;
    }

    // Get transaction count for this category
    public function getTransactionCountAttribute()
    {
        return $this->transactions()->count();
    }

    // Get transaction count including subcategories (for parent categories)
    public function getTransactionCountWithChildrenAttribute()
    {
        if ($this->isParent()) {
            // For parent categories, count all children's transactions using efficient DB query
            $childrenIds = $this->children->pluck('id')->toArray();
            
            // If no children, just return parent's count
            if (empty($childrenIds)) {
                return $this->transactions()->count();
            }
            
            // Include parent ID as well (in case parent has direct transactions)
            $allIds = array_merge($childrenIds, [$this->id]);
            
            return Transaction::whereIn('category_id', $allIds)->count();
        }
        
        return $this->transaction_count;
    }
}
