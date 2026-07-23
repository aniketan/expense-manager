<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AccountTransferService
{
    public function pair(Transaction $transaction): array
    {
        return $this->findGroupLegs($transaction, false);
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $categories = $this->transferCategories();
            $groupId = (string) Str::uuid();
            $common = $this->commonAttributes($data, $groupId);

            $outgoing = Transaction::create(array_merge($common, [
                'account_id' => $data['account_id'],
                'category_id' => $categories['outgoing']->id,
            ]));

            $incoming = Transaction::create(array_merge($common, [
                'account_id' => $data['transfer_to_account_id'],
                'category_id' => $categories['incoming']->id,
            ]));

            return [$outgoing, $incoming];
        });
    }

    public function update(Transaction $transaction, array $data): array
    {
        return DB::transaction(function () use ($transaction, $data) {
            $legs = $this->findGroupLegs($transaction);
            $categories = $this->transferCategories();
            $common = $this->commonAttributes($data, $transaction->transfer_group_id);
            $outgoing = $legs['outgoing'];
            $incoming = $legs['incoming'];

            $outgoing->update(array_merge($common, [
                'account_id' => $data['account_id'],
                'category_id' => $categories['outgoing']->id,
            ]));
            $incoming->update(array_merge($common, [
                'account_id' => $data['transfer_to_account_id'],
                'category_id' => $categories['incoming']->id,
            ]));

            return [$outgoing->fresh(), $incoming->fresh()];
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            foreach ($this->findGroupLegs($transaction) as $leg) {
                $leg->delete();
            }
        });
    }

    private function findGroupLegs(Transaction $transaction, bool $lock = true): array
    {
        if (! $transaction->transfer_group_id) {
            throw new RuntimeException('Transfer is missing its group ID.');
        }

        $query = Transaction::query()
            ->where('transfer_group_id', $transaction->transfer_group_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $legs = $query->get();

        if ($legs->count() !== 2) {
            throw new RuntimeException('Transfer group must contain exactly two legs.');
        }

        $byCode = $legs->keyBy(fn (Transaction $leg) => $leg->category?->code);
        if (! $byCode->has(Transaction::CATEGORY_TRANSFER_INCOMING)
            || ! $byCode->has(Transaction::CATEGORY_TRANSFER_OUTGOING)) {
            throw new RuntimeException('Transfer group has invalid directions.');
        }

        return [
            'incoming' => $byCode[Transaction::CATEGORY_TRANSFER_INCOMING],
            'outgoing' => $byCode[Transaction::CATEGORY_TRANSFER_OUTGOING],
        ];
    }

    private function transferCategories(): array
    {
        $categories = Category::query()
            ->whereIn('code', [
                Transaction::CATEGORY_TRANSFER_INCOMING,
                Transaction::CATEGORY_TRANSFER_OUTGOING,
            ])
            ->get()
            ->keyBy('code');

        if (! $categories->has(Transaction::CATEGORY_TRANSFER_INCOMING)
            || ! $categories->has(Transaction::CATEGORY_TRANSFER_OUTGOING)) {
            throw new RuntimeException('Account transfer categories are not configured.');
        }

        return [
            'incoming' => $categories[Transaction::CATEGORY_TRANSFER_INCOMING],
            'outgoing' => $categories[Transaction::CATEGORY_TRANSFER_OUTGOING],
        ];
    }

    private function commonAttributes(array $data, string $groupId): array
    {
        return [
            'transaction_type' => Transaction::TYPE_TRANSFER,
            'transfer_group_id' => $groupId,
            'amount' => $data['amount'],
            'tax' => $data['tax'] ?? null,
            'description' => $data['description'] ?? null,
            'payee_payer' => $data['payee_payer'] ?? null,
            'notes' => $data['notes'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'transaction_time' => $data['transaction_time'] ?? null,
            'status' => $data['status'] ?? Transaction::STATUS_CLEARED,
            'payment_method' => null,
            'reference_number' => $data['reference_number'] ?? null,
            'tags' => $data['tags'] ?? null,
            'location' => $data['location'] ?? null,
        ];
    }
}
