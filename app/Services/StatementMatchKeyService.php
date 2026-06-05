<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonInterface;

/**
 * Builds deterministic statement-vs-ledger fingerprints from date, amount, and normalized description.
 */
class StatementMatchKeyService
{
    public function normalizeDescription(string $description): string
    {
        $desc = strtolower($description);
        if (preg_match('/upi\/\d+/i', $desc, $m)) {
            return strtolower($m[0]);
        }

        return substr((string) preg_replace('/[^a-z0-9]/', '', $desc), 0, 30);
    }

    /**
     * @param  float|string  $amount  Positive magnitude (debit or credit from statement).
     */
    public function formatAmountForKey(float|string $amount): string
    {
        return sprintf('%.2f', round((float) $amount, 2));
    }

    /**
     * @param  float|string  $amount  Positive magnitude matching imported transactions.amount.
     */
    public function matchKey(string $dateYmd, float|string $amount, string $description): string
    {
        return $dateYmd.'_'.$this->formatAmountForKey($amount).'_'.$this->normalizeDescription($description);
    }

    /**
     * Secondary reconcile fingerprint (amount + normalized description, no calendar date).
     */
    public function matchKeyAmountNormDesc(float|string $amount, string $description): string
    {
        return $this->formatAmountForKey($amount).'_'.$this->normalizeDescription($description);
    }

    /**
     * Thin-only bucket without date (pair thin ledger rows when statement date differs).
     */
    public function thinAmountOnlyBucketKey(float|string $amount): string
    {
        return $this->formatAmountForKey($amount).'_thin_bucket';
    }

    /**
     * DB Transaction → same key shape as parsed statement rows.
     */
    public function matchKeyFromDbTransaction(Transaction $transaction): string
    {
        $date = $transaction->transaction_date instanceof CarbonInterface
            ? $transaction->transaction_date->format('Y-m-d')
            : (string) $transaction->transaction_date;

        return $this->matchKey($date, (float) $transaction->amount, (string) ($transaction->description ?? ''));
    }

    public function isDescriptionThin(?string $description): bool
    {
        $t = trim((string) $description);
        if ($t === '') {
            return true;
        }
        if (strlen($t) < 4) {
            return true;
        }
        if (stripos($t, 'Synced from external DB') !== false) {
            return true;
        }

        return false;
    }
}
