<?php

namespace App\Reporting;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * One description of "which transactions" shared by the web list, CSV export,
 * dashboard, AI tools, and MCP, so every surface filters the same way.
 */
final class TransactionFilters
{
    public const PERIODS = ['today', 'this_week', 'this_month', 'last_month', 'all'];

    /**
     * @param  list<string>|null  $types  null = every type (including transfers)
     * @param  list<int>|null  $categoryIds  already expanded to include subcategories
     * @param  array<string, string>  $ui  raw request values echoed back to the web filter form
     */
    public function __construct(
        public readonly ?array $types = null,
        public readonly ?array $categoryIds = null,
        public readonly ?array $accountIds = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?string $search = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $status = null,
        public readonly ?string $tags = null,
        public readonly ?string $cashFlow = null,
        public readonly array $ui = [],
    ) {}

    public static function all(): self
    {
        return new self;
    }

    /**
     * Income and expense only: what "totals" and "both" mean everywhere. Transfers only
     * move money between accounts, so they never count as income or spending.
     */
    public static function incomeAndExpense(): self
    {
        return new self(types: [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE]);
    }

    /**
     * @return array{0: ?string, 1: ?string} Inclusive [from, to] dates for a named period.
     */
    public static function periodRange(string $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match ($period) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'this_week' => [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()],
            'this_month' => [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
            'last_month' => [
                $now->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            default => [null, null],
        };
    }

    public function forPeriod(string $period): self
    {
        [$from, $to] = self::periodRange($period);

        return $this->with(['dateFrom' => $from, 'dateTo' => $to]);
    }

    /**
     * "income" | "expense" | "transfer" | "both" (income + expense) | "all"/null.
     */
    public function ofType(?string $type): self
    {
        return $this->with(['types' => match ($type) {
            null, '', 'all' => null,
            'both' => [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE],
            default => [$type],
        }]);
    }

    /**
     * @param  list<int>  $categoryIds  parents are expanded to include their subcategories
     */
    public function inCategories(array $categoryIds): self
    {
        return $this->with(['categoryIds' => TransactionQuery::expandCategoryIds($categoryIds)]);
    }

    /**
     * The web transaction list and CSV export query string.
     */
    public static function fromRequest(Request $request): self
    {
        $ui = [];
        $args = [];

        foreach (['search', 'account', 'date_from', 'date_to', 'payment_method', 'tags'] as $key) {
            if ($request->filled($key)) {
                $ui[$key] = $request->get($key);
            }
        }

        if ($request->filled('category')) {
            $ui['category'] = $request->get('category');
        }

        if ($request->filled('subcategory')) {
            $ui['subcategory'] = $request->get('subcategory');
            $args['categoryIds'] = [(int) $request->get('subcategory')];
        } elseif ($request->filled('category')) {
            $args['categoryIds'] = TransactionQuery::expandCategoryIds([(int) $request->get('category')]);
        }

        if ($request->filled('status') && in_array($request->get('status'), Transaction::STATUSES, true)) {
            $ui['status'] = $request->get('status');
            $args['status'] = $ui['status'];
        }

        // cash_flow (credit/debit) takes precedence over an explicit transaction_type.
        $cashFlow = $request->get('cash_flow');
        if (in_array($cashFlow, ['credit', 'debit'], true)) {
            $ui['cash_flow'] = $cashFlow;
            $args['cashFlow'] = $cashFlow;
        } elseif ($request->filled('transaction_type')) {
            $ui['transaction_type'] = $request->get('transaction_type');
            $args['types'] = [$ui['transaction_type']];
        }

        return new self(
            types: $args['types'] ?? null,
            categoryIds: $args['categoryIds'] ?? null,
            accountIds: isset($ui['account']) ? [(int) $ui['account']] : null,
            dateFrom: $ui['date_from'] ?? null,
            dateTo: $ui['date_to'] ?? null,
            search: $ui['search'] ?? null,
            paymentMethod: $ui['payment_method'] ?? null,
            status: $args['status'] ?? null,
            tags: $ui['tags'] ?? null,
            cashFlow: $args['cashFlow'] ?? null,
            ui: $ui,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
