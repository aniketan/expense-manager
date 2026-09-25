<?php

namespace App\Services;

use App\Models\Transaction;
use App\Services\StatementReconciliation\BalanceChainAnalyzer;
use App\Services\StatementReconciliation\BundleMatcher;
use App\Services\StatementReconciliation\ReconciliationAnnotator;
use App\Services\StatementReconciliation\StatementCandidateIndex;
use App\Services\StatementReconciliation\StatementMatcher;
use Carbon\Carbon;

class StatementReconciliationService
{
    public function __construct(
        private StatementMatchKeyService $matchKeys,
        private ?StatementCandidateIndex $candidateIndex = null,
        private ?StatementMatcher $statementMatcher = null,
        private ?BundleMatcher $bundleMatcher = null,
        private ?BalanceChainAnalyzer $balanceAnalyzer = null,
        private ?ReconciliationAnnotator $annotator = null,
    ) {
        $this->candidateIndex = $candidateIndex ?? new StatementCandidateIndex($matchKeys);
        $this->statementMatcher = $statementMatcher ?? new StatementMatcher($matchKeys);
        $this->bundleMatcher = $bundleMatcher ?? new BundleMatcher;
        $this->balanceAnalyzer = $balanceAnalyzer ?? new BalanceChainAnalyzer;
        $this->annotator = $annotator ?? new ReconciliationAnnotator($matchKeys);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions  Rows from StatementParserService::parse
     * @return array{
     *     period: array{start: string|null, end: string|null},
     *     summary: array<string, mixed>,
     *     transactions: array<int, array<string, mixed>>
     * }
     */
    public function analyze(array $parsedTransactions, ?int $accountId): array
    {
        $period = $this->candidateIndex->derivePeriod($parsedTransactions);
        $skewDays = max(0, (int) config('statement.reconcile_date_skew_days', 3));
        $thinProximityMaxDays = config('statement.reconcile_thin_proximity_max_days');
        $thinProximityMaxDays = $thinProximityMaxDays !== null ? max(0, (int) $thinProximityMaxDays) : null;
        $debugReconcile = (bool) config('statement.reconcile_debug', false);

        $expandedStart = null;
        $expandedEnd = null;
        if ($period['start'] && $period['end']) {
            $expandedStart = Carbon::parse($period['start'])->subDays($skewDays)->format('Y-m-d');
            $expandedEnd = Carbon::parse($period['end'])->addDays($skewDays)->format('Y-m-d');
        }

        $balanceSummary = $this->balanceAnalyzer->computeBalanceSummary(
            $parsedTransactions,
            $accountId,
            $expandedStart,
            $expandedEnd,
        );

        if ($accountId === null || $parsedTransactions === []) {
            return [
                'period' => $period,
                'summary' => [
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'missing_in_db' => 0,
                    'matched_complete' => 0,
                    'matched_needs_enrichment' => 0,
                    'balance' => $balanceSummary,
                ],
                'transactions' => $this->annotator->annotateUnscoped($parsedTransactions),
            ];
        }

        $dbExpanded = Transaction::query()
            ->where('account_id', $accountId)
            ->with(['category.parent'])
            ->when($expandedStart && $expandedEnd, function ($q) use ($expandedStart, $expandedEnd): void {
                $q->whereDate('transaction_date', '>=', $expandedStart)
                    ->whereDate('transaction_date', '<=', $expandedEnd);
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $index = $this->candidateIndex->buildIndex($dbExpanded);

        $statementNet = $this->balanceAnalyzer->statementNetDelta($parsedTransactions);
        $narrowDb = $this->candidateIndex->narrowDbTransactions($dbExpanded, $period);
        $dbNet = $this->balanceAnalyzer->dbNetDelta($narrowDb);
        $balanceSummary['net_delta_statement_minus_db'] = round($statementNet - $dbNet, 2);

        $bundleAssignments = $this->bundleMatcher->resolveBundleAssignments($parsedTransactions, $accountId);

        [$matches, $matchMeta] = $this->statementMatcher->match(
            $parsedTransactions,
            $index,
            $bundleAssignments,
            $thinProximityMaxDays,
        );

        $annotation = $this->annotator->annotate(
            $parsedTransactions,
            $matches,
            $matchMeta,
            $accountId,
            $debugReconcile,
        );

        return [
            'period' => $period,
            'summary' => [
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'missing_in_db' => $annotation['counts']['missing_in_db'],
                'matched_complete' => $annotation['counts']['matched_complete'],
                'matched_needs_enrichment' => $annotation['counts']['matched_needs_enrichment'],
                'balance' => $balanceSummary,
            ],
            'transactions' => $annotation['transactions'],
        ];
    }
}
