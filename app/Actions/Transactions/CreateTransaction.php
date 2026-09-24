<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Services\AccountTransferService;

/**
 * Creates a transaction from any entry point (web, statement import, AI tools, MCP)
 * with the same domain rules. Callers validate input formats; this owns the invariants.
 */
class CreateTransaction
{
    public function __construct(
        private readonly AccountTransferService $transfers,
        private readonly ResolveCategory $categories,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Transaction attributes; transfers also need transfer_to_account_id.
     * @return Transaction The created row, or the outgoing leg for a transfer.
     */
    public function handle(array $data): Transaction
    {
        $type = $data['transaction_type'] ?? null;

        if (! in_array($type, [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE, Transaction::TYPE_TRANSFER], true)) {
            throw TransactionRuleViolation::on('transaction_type', 'Transaction type must be income, expense, or transfer.');
        }

        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw TransactionRuleViolation::on('amount', 'Amount must be greater than zero.');
        }

        $data['status'] ??= Transaction::STATUS_CLEARED;

        if ($type === Transaction::TYPE_TRANSFER) {
            if (empty($data['transfer_to_account_id']) || (int) $data['transfer_to_account_id'] === (int) ($data['account_id'] ?? 0)) {
                throw TransactionRuleViolation::on('transfer_to_account_id', 'Choose a destination account different from the source account.');
            }

            [$outgoing] = $this->transfers->create($data);

            return $outgoing;
        }

        unset($data['transfer_to_account_id']);
        $this->categories->ensureCompatible($data['category_id'] ?? null, $type);

        return Transaction::create($data);
    }
}
