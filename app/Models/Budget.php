<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    /**
     * Preloaded spent value set by preloadSpentFor(); null means "not loaded",
     * so a single-budget caller falls back to the per-budget query below.
     */
    protected ?float $preloadedSpent = null;

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
        // Preloaded for whole-collection listing pages (issue #64) — skip the query.
        if ($this->preloadedSpent !== null) {
            return $this->preloadedSpent;
        }

        return $this->calculateSpent();
    }

    /**
     * The exact same expense-only, boundary-inclusive sum a single-budget
     * caller would get from getSpentAmountAttribute().
     */
    public function calculateSpent(): float
    {
        return (float) Transaction::whereIn('category_id', $this->trackedCategoryIds())
            ->whereBetween('transaction_date', [$this->start_date, $this->end_date])
            ->where('transaction_type', Transaction::TYPE_EXPENSE)
            ->sum('amount');
    }

    /**
     * Load spent_amount for every budget in the collection in one bounded set of
     * queries. Callers that later read the accessors get the preloaded value, so
     * a listing page costs the same number of queries no matter how many budgets
     * it shows. Budgets without a preloaded value keep the old query behavior.
     *
     * @param  iterable<int, Budget>|Collection<int, Budget>  $budgets
     */
    public static function preloadSpentFor(iterable $budgets): void
    {
        $budgets = $budgets instanceof Collection ? $budgets : collect($budgets);

        if ($budgets->isEmpty()) {
            return;
        }

        // Resolve each budget's tracked categories without lazy-loading the
        // category relation per budget (children of the budget's category).
        $parentIds = $budgets->pluck('category_id')->filter()->unique()->values()->all();

        $childrenByParent = Category::query()
            ->whereIn('parent_id', $parentIds)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($children) => $children->pluck('id')->all());

        $trackedByBudget = [];
        foreach ($budgets as $budget) {
            $trackedByBudget[$budget->getKey()] = array_merge(
                [$budget->category_id],
                $childrenByParent->get($budget->category_id, [])
            );
        }

        // One query covering every budget's range: expenses in the tracked
        // categories between the earliest start and the latest end, then summed
        // per budget in PHP. Only the date part is compared: SQLite stores
        // 'Y-m-d 00:00:00' while MySQL/Postgres DATE columns return 'Y-m-d', and
        // comparing full strings would drop each budget's first day on the latter.
        $allTrackedIds = array_values(array_unique(array_merge(...array_values($trackedByBudget))));
        $minStart = $budgets->min(fn (Budget $budget) => $budget->start_date)->toDateString();
        $maxEnd = $budgets->max(fn (Budget $budget) => $budget->end_date)->toDateString();

        $rowsByCategory = [];
        foreach (Transaction::query()
            ->toBase()
            ->select(['category_id', 'transaction_date', 'amount'])
            ->where('transaction_type', Transaction::TYPE_EXPENSE)
            ->whereIn('category_id', $allTrackedIds)
            ->whereDate('transaction_date', '>=', $minStart)
            ->whereDate('transaction_date', '<=', $maxEnd)
            ->get() as $row) {
            $rowsByCategory[(int) $row->category_id][] = [substr((string) $row->transaction_date, 0, 10), (float) $row->amount];
        }

        foreach ($budgets as $budget) {
            $start = $budget->start_date->toDateString();
            $end = $budget->end_date->toDateString();
            $spent = 0.0;

            foreach ($trackedByBudget[$budget->getKey()] as $categoryId) {
                foreach ($rowsByCategory[$categoryId] ?? [] as [$date, $amount]) {
                    if ($date >= $start && $date <= $end) {
                        $spent += $amount;
                    }
                }
            }

            $budget->setPreloadedSpent(round($spent, 2));
        }
    }

    /**
     * @param  float  $amount  the already-computed spent value
     */
    public function setPreloadedSpent(float $amount): static
    {
        $this->preloadedSpent = $amount;

        return $this;
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
