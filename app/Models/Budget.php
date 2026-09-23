<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        $today = now()->toDateString();

        // Compare date parts so a budget ending today stays current all day.
        return $query->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
    }

    /**
     * The budget's category plus its children: the set spending is measured against.
     *
     * @return array<int, int>
     */
    public function trackedCategoryIds(): array
    {
        return array_merge([$this->category_id], $this->category->children->pluck('id')->all());
    }

    // Get spent amount for this budget (includes child categories)
    public function getSpentAmountAttribute()
    {
        return Transaction::whereIn('category_id', $this->trackedCategoryIds())
            ->whereBetween('transaction_date', [$this->start_date, $this->end_date])
            ->where('transaction_type', 'expense')
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
}
