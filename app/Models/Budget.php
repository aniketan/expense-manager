<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'amount',
        'period_type',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Period types
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY = 'yearly';
    public const PERIOD_CUSTOM = 'custom';

    public static function getPeriodTypes(): array
    {
        return [
            self::PERIOD_MONTHLY => 'Monthly',
            self::PERIOD_YEARLY => 'Yearly',
            self::PERIOD_CUSTOM => 'Custom',
        ];
    }

    // Relationship: Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scope for active budgets
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for current budgets (within date range)
    public function scopeCurrent($query)
    {
        $today = now();
        return $query->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today);
    }

    // Get spent amount for this budget
    public function getSpentAmountAttribute()
    {
        return $this->category->transactions()
            ->whereBetween('date', [$this->start_date, $this->end_date])
            ->where('type', 'expense')
            ->sum('amount');
    }

    // Get remaining amount
    public function getRemainingAmountAttribute()
    {
        return max(0, $this->amount - $this->spent_amount);
    }

    // Get percentage used
    public function getPercentageUsedAttribute()
    {
        if ($this->amount <= 0) {
            return 0;
        }
        return min(100, round(($this->spent_amount / $this->amount) * 100, 1));
    }

    // Get status (success, warning, danger)
    public function getStatusAttribute()
    {
        $percentage = $this->percentage_used;

        if ($percentage >= 100) {
            return 'danger'; // Exceeded
        } elseif ($percentage >= 80) {
            return 'warning'; // Close to limit
        } else {
            return 'success'; // Within budget
        }
    }

    // Check if budget is exceeded
    public function isExceeded()
    {
        return $this->spent_amount >= $this->amount;
    }

    // Check if budget is in current period
    public function isCurrent()
    {
        $today = now();
        return $today->between($this->start_date, $this->end_date);
    }
}
