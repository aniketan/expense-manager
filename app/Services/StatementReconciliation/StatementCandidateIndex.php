<?php

namespace App\Services\StatementReconciliation;

use App\Models\Transaction;
use App\Services\StatementMatchKeyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatementCandidateIndex
{
    public function __construct(
        private StatementMatchKeyService $matchKeys,
    ) {}

    /**
     * @param  Collection<int, Transaction>|\Illuminate\Database\Eloquent\Collection<int, Transaction>  $dbExpanded
     * @return array{
     *     firstByKey: array<string, Transaction>,
     *     thinBucketsMulti: array<string, array<int, Transaction>>,
     *     secondaryBuckets: array<string, array<int, Transaction>>,
     *     thinAmountBuckets: array<string, array<int, Transaction>>
     * }
     */
    public function buildIndex(Collection $dbExpanded): array
    {
        /** @var array<string, Transaction> $firstByKey */
        $firstByKey = [];
        /** @var array<string, array<int, Transaction>> $thinBucketsMulti */
        $thinBucketsMulti = [];
        /** @var array<string, array<int, Transaction>> $secondaryBuckets */
        $secondaryBuckets = [];
        /** @var array<string, array<int, Transaction>> $thinAmountBuckets */
        $thinAmountBuckets = [];

        foreach ($dbExpanded as $txn) {
            $fk = $this->matchKeys->matchKeyFromDbTransaction($txn);
            if (! isset($firstByKey[$fk])) {
                $firstByKey[$fk] = $txn;
            }

            $descDb = (string) ($txn->description ?? '');
            $amt = (float) $txn->amount;
            $secKey = $this->matchKeys->matchKeyAmountNormDesc($amt, $descDb);
            $secondaryBuckets[$secKey][] = $txn;

            $dateDb = $txn->transaction_date instanceof Carbon
                ? $txn->transaction_date->format('Y-m-d')
                : Carbon::parse((string) $txn->transaction_date)->format('Y-m-d');

            if ($this->matchKeys->isDescriptionThin($descDb)) {
                $bk = $this->thinDbBucketKey($dateDb, $amt);
                $thinBucketsMulti[$bk][] = $txn;
                $amtKey = $this->matchKeys->thinAmountOnlyBucketKey($amt);
                $thinAmountBuckets[$amtKey][] = $txn;
            }
        }

        return [
            'firstByKey' => $firstByKey,
            'thinBucketsMulti' => $thinBucketsMulti,
            'secondaryBuckets' => $secondaryBuckets,
            'thinAmountBuckets' => $thinAmountBuckets,
        ];
    }

    /**
     * @param  Collection<int, Transaction>|\Illuminate\Database\Eloquent\Collection<int, Transaction>  $dbExpanded
     * @param  array{start: string|null, end: string|null}  $period
     * @return Collection<int, Transaction>
     */
    public function narrowDbTransactions(Collection $dbExpanded, array $period): Collection
    {
        if ($period['start'] === null || $period['end'] === null) {
            return $dbExpanded;
        }

        return $dbExpanded->filter(function (Transaction $txn) use ($period): bool {
            $d = $txn->transaction_date instanceof Carbon
                ? $txn->transaction_date->format('Y-m-d')
                : Carbon::parse((string) $txn->transaction_date)->format('Y-m-d');

            return $d >= $period['start'] && $d <= $period['end'];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedTransactions
     * @return array{start: string|null, end: string|null}
     */
    public function derivePeriod(array $parsedTransactions): array
    {
        $dates = [];
        foreach ($parsedTransactions as $row) {
            $d = $row['date'] ?? null;
            if (is_string($d) && $d !== '') {
                try {
                    $dates[] = Carbon::parse($d)->format('Y-m-d');
                } catch (\Throwable) {
                    //
                }
            }
        }
        if ($dates === []) {
            return ['start' => null, 'end' => null];
        }

        sort($dates);

        return ['start' => $dates[0], 'end' => $dates[count($dates) - 1]];
    }

    public function thinDbBucketKey(string $dateYmd, float $amount): string
    {
        return $dateYmd.'_'.$this->matchKeys->formatAmountForKey($amount).'_thin_bucket';
    }
}
