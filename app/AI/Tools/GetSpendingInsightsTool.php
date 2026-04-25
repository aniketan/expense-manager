<?php

namespace App\AI\Tools;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Prism\Prism\Tool;

class GetSpendingInsightsTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('get_spending_insights')
            ->for(
                'Analyse financial data. Use query_type to choose what to retrieve: ' .
                '"spending_summary" for income/expense overview, ' .
                '"budget_status" to check budgets (over/under), ' .
                '"payment_breakdown" to see UPI vs cash vs card totals, ' .
                '"tag_summary" to see spending by tag, ' .
                '"day_breakdown" to find the busiest spending day of the week.'
            )
            ->withEnumParameter(
                'query_type',
                'What kind of insight to return',
                ['spending_summary', 'budget_status', 'payment_breakdown', 'tag_summary', 'day_breakdown']
            )
            ->withEnumParameter(
                'period',
                'Time period for the query (not used for budget_status)',
                ['today', 'this_week', 'this_month', 'last_month', 'all'],
                false
            )
            ->withStringParameter(
                'category_name',
                'Optional: filter budget_status to a specific category name.',
                false
            )
            ->using($this->execute(...));
    }

    public function execute(
        string  $query_type,
        ?string $period       = 'this_month',
        ?string $category_name = null,
    ): string {
        try {
            return match ($query_type) {
                'spending_summary'   => $this->spendingSummary($period ?? 'this_month'),
                'budget_status'      => $this->budgetStatus($category_name),
                'payment_breakdown'  => $this->paymentBreakdown($period ?? 'this_month'),
                'tag_summary'        => $this->tagSummary($period ?? 'all'),
                'day_breakdown'      => $this->dayBreakdown($period ?? 'this_month'),
                default              => json_encode(['success' => false, 'error' => 'Unknown query_type.']),
            };
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'Insight query failed: ' . $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------

    private function applyPeriod($query, string $period)
    {
        match ($period) {
            'today'      => $query->whereDate('transaction_date', Carbon::today()),
            'this_week'  => $query->whereBetween('transaction_date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]),
            'this_month' => $query->whereYear('transaction_date', Carbon::now()->year)
                ->whereMonth('transaction_date', Carbon::now()->month),
            'last_month' => $query->whereYear('transaction_date', Carbon::now()->subMonth()->year)
                ->whereMonth('transaction_date', Carbon::now()->subMonth()->month),
            default      => null, // 'all' — no filter
        };

        return $query;
    }

    private function spendingSummary(string $period): string
    {
        $base = $this->applyPeriod(Transaction::query(), $period);

        $income  = (clone $base)->where('transaction_type', 'income')->sum('amount');
        $expense = (clone $base)->where('transaction_type', 'expense')->sum('amount');
        $net     = $income - $expense;
        $savings = $income > 0 ? round(($net / $income) * 100, 1) : 0;

        $topCategories = (clone $base)
            ->where('transaction_type', 'expense')
            ->with('category')
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category?->name ?? 'Uncategorized',
                'total'    => round((float) $r->total, 2),
                'count'    => (int) $r->cnt,
            ])->values()->all();

        return json_encode([
            'success'        => true,
            'query_type'     => 'spending_summary',
            'period'         => $period,
            'income'         => round((float) $income, 2),
            'expense'        => round((float) $expense, 2),
            'net'            => round((float) $net, 2),
            'savings_rate'   => $savings . '%',
            'top_categories' => $topCategories,
        ]);
    }

    private function budgetStatus(?string $categoryName): string
    {
        $query = Budget::with(['category.children'])
            ->active()
            ->current();

        if (!empty($categoryName)) {
            $query->whereHas('category', fn ($q) =>
                $q->where('name', 'like', '%' . $categoryName . '%')
            );
        }

        $budgets = $query->get();

        if ($budgets->isEmpty()) {
            return json_encode([
                'success' => true,
                'query_type' => 'budget_status',
                'budgets' => [],
                'message' => 'No active budgets found' . ($categoryName ? " for \"{$categoryName}\"" : '') . '.',
            ]);
        }

        $result = $budgets->map(fn (Budget $b) => [
            'name'           => $b->name,
            'category'       => $b->category?->name,
            'limit'          => round((float) $b->amount, 2),
            'spent'          => round((float) $b->spent_amount, 2),
            'remaining'      => round((float) $b->remaining_amount, 2),
            'percent_used'   => $b->percentage_used . '%',
            'status'         => $b->status, // success / warning / danger
            'period_type'    => $b->period_type,
            'start_date'     => $b->start_date->format('Y-m-d'),
            'end_date'       => $b->end_date->format('Y-m-d'),
        ])->values()->all();

        $exceeded = collect($result)->where('status', 'danger')->count();
        $warning  = collect($result)->where('status', 'warning')->count();

        return json_encode([
            'success'          => true,
            'query_type'       => 'budget_status',
            'total_budgets'    => count($result),
            'exceeded'         => $exceeded,
            'warning'          => $warning,
            'on_track'         => count($result) - $exceeded - $warning,
            'budgets'          => $result,
        ]);
    }

    private function paymentBreakdown(string $period): string
    {
        $base = $this->applyPeriod(
            Transaction::query()->where('transaction_type', 'expense'),
            $period
        );

        $rows = (clone $base)
            ->selectRaw('COALESCE(payment_method, "Unknown") as method, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $breakdown = $rows->map(fn ($r) => [
            'method' => $r->method,
            'total'  => round((float) $r->total, 2),
            'count'  => (int) $r->cnt,
        ])->values()->all();

        return json_encode([
            'success'    => true,
            'query_type' => 'payment_breakdown',
            'period'     => $period,
            'breakdown'  => $breakdown,
        ]);
    }

    private function tagSummary(string $period): string
    {
        $base = $this->applyPeriod(Transaction::query(), $period);

        $rows = (clone $base)
            ->whereNotNull('tags')
            ->where('tags', '!=', '')
            ->get(['tags', 'amount']);

        // Explode comma-separated tags and aggregate
        $tagMap = [];
        foreach ($rows as $row) {
            $tags = array_filter(array_map('trim', explode(',', $row->tags)));
            foreach ($tags as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = ['tag' => $tag, 'total' => 0.0, 'count' => 0];
                }
                $tagMap[$tag]['total'] += (float) $row->amount;
                $tagMap[$tag]['count']++;
            }
        }

        usort($tagMap, fn ($a, $b) => $b['total'] <=> $a['total']);
        $tagMap = array_values(array_map(fn ($t) => [
            'tag'   => $t['tag'],
            'total' => round($t['total'], 2),
            'count' => $t['count'],
        ], $tagMap));

        return json_encode([
            'success'    => true,
            'query_type' => 'tag_summary',
            'period'     => $period,
            'total_tags' => count($tagMap),
            'tags'       => $tagMap,
        ]);
    }

    private function dayBreakdown(string $period): string
    {
        $base = $this->applyPeriod(
            Transaction::query()->where('transaction_type', 'expense'),
            $period
        );

        $rows = (clone $base)->get(['transaction_date', 'amount']);

        $dayMap = [];
        $dayCounts = [];
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($rows as $row) {
            $dow = (int) $row->transaction_date->dayOfWeek; // 0=Sun
            if (!isset($dayMap[$dow])) {
                $dayMap[$dow]   = 0.0;
                $dayCounts[$dow] = 0;
            }
            $dayMap[$dow]   += (float) $row->amount;
            $dayCounts[$dow]++;
        }

        $breakdown = [];
        foreach ($dayNames as $idx => $name) {
            $total = $dayMap[$idx] ?? 0.0;
            $count = $dayCounts[$idx] ?? 0;
            $breakdown[] = [
                'day'     => $name,
                'total'   => round($total, 2),
                'count'   => $count,
                'average' => $count > 0 ? round($total / $count, 2) : 0,
            ];
        }

        usort($breakdown, fn ($a, $b) => $b['total'] <=> $a['total']);
        $busiest = $breakdown[0]['day'] ?? 'N/A';

        return json_encode([
            'success'      => true,
            'query_type'   => 'day_breakdown',
            'period'       => $period,
            'busiest_day'  => $busiest,
            'breakdown'    => $breakdown,
        ]);
    }
}
