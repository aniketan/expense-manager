<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Services\AccountTransferService;

/**
 * Applies full (web form) or partial (AI, statement enrichment) changes to a transaction.
 * Transfers are always updated as a pair; regular rows keep a type-compatible category.
 */
class UpdateTransaction
{
    /** Attributes a transfer shares across both legs. */
    private const TRANSFER_FIELDS = [
        'account_id', 'transfer_to_account_id', 'amount', 'tax', 'description', 'payee_payer', 'notes',
        'transaction_date', 'transaction_time', 'status', 'reference_number', 'tags', 'location',
    ];

    public function __construct(
        private readonly AccountTransferService $transfers,
        private readonly ResolveCategory $categories,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  Only the keys present are changed.
     */
    public function handle(Transaction $transaction, array $changes): Transaction
    {
        if ($this->transfers->isUnlinked($transaction)) {
            throw TransactionRuleViolation::on('transaction', 'This transfer has no linked counterpart (created before linked transfers existed) and cannot be edited. Delete it and record the transfer again.');
        }

        $isTransfer = $transaction->transaction_type === Transaction::TYPE_TRANSFER;
        $newType = $changes['transaction_type'] ?? $transaction->transaction_type;

        if ($isTransfer !== ($newType === Transaction::TYPE_TRANSFER)) {
            throw TransactionRuleViolation::on('transaction_type', 'Converting between transfers and regular transactions is not supported.');
        }

        if (array_key_exists('amount', $changes) && (float) $changes['amount'] <= 0) {
            throw TransactionRuleViolation::on('amount', 'Amount must be greater than zero.');
        }

        if (array_key_exists('status', $changes) && $changes['status'] === null) {
            $changes['status'] = Transaction::STATUS_CLEARED;
        }

        return $isTransfer
            ? $this->updateTransfer($transaction, $changes)
            : $this->updateRegular($transaction, $changes, $newType);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function updateTransfer(Transaction $transaction, array $changes): Transaction
    {
        // Transfer legs keep their fixed direction categories; any category input is ignored.
        $legs = $this->transfers->pair($transaction);
        $outgoing = $legs['outgoing'];

        $current = [
            'account_id' => $outgoing->account_id,
            'transfer_to_account_id' => $legs['incoming']->account_id,
            'amount' => $outgoing->amount,
            'tax' => $outgoing->tax,
            'description' => $outgoing->description,
            'payee_payer' => $outgoing->payee_payer,
            'notes' => $outgoing->notes,
            'transaction_date' => $outgoing->transaction_date->toDateString(),
            'transaction_time' => $outgoing->transaction_time,
            'status' => $outgoing->status,
            'reference_number' => $outgoing->reference_number,
            'tags' => $outgoing->tags,
            'location' => $outgoing->location,
        ];
        $payload = array_merge($current, array_intersect_key($changes, array_flip(self::TRANSFER_FIELDS)));

        if ((int) $payload['transfer_to_account_id'] === (int) $payload['account_id']) {
            throw TransactionRuleViolation::on('transfer_to_account_id', 'Choose a destination account different from the source account.');
        }

        [$updatedOutgoing] = $this->transfers->update($transaction, $payload);

        return $updatedOutgoing;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function updateRegular(Transaction $transaction, array $changes, string $newType): Transaction
    {
        unset($changes['transfer_to_account_id']);

        $categoryId = $changes['category_id'] ?? $transaction->category_id;

        // Only re-check when the category or type actually changes, so older rows filed
        // under a now-mismatched category can still be edited for other fields.
        if ((int) $categoryId !== (int) $transaction->category_id || $newType !== $transaction->transaction_type) {
            $this->categories->ensureCompatible($categoryId, $newType);
        }

        $transaction->update($changes);

        return $transaction->fresh();
    }
}
