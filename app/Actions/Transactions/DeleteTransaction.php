<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Services\AccountTransferService;

/**
 * Deletes a transaction; a linked transfer always goes with its counterpart leg.
 */
class DeleteTransaction
{
    public function __construct(private readonly AccountTransferService $transfers) {}

    /**
     * @return int Number of rows deleted (2 for a linked transfer).
     */
    public function handle(Transaction $transaction): int
    {
        if ($transaction->transaction_type !== Transaction::TYPE_TRANSFER) {
            return $transaction->delete() ? 1 : 0;
        }

        $rows = $this->transfers->isUnlinked($transaction) ? 1 : 2;
        $this->transfers->delete($transaction);

        return $rows;
    }
}
