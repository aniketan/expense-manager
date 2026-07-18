<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_TRANSFER = 'transfer';

    public const CATEGORY_TRANSFER_INCOMING = 'TRANSFER_INCOMING';

    public const CATEGORY_TRANSFER_OUTGOING = 'TRANSFER_OUTGOING';

    protected $table = 'transactions';

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['category.parent', 'account'];

    protected $fillable = [
        'account_id',
        'category_id',
        'transaction_type',
        'transfer_group_id',
        'amount',
        'description',
        'transaction_date',
        'transaction_time',
        'payment_method',
        'reference_number',
        'tags',
        'location',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        // When a transaction is created, update the account balance
        static::created(function ($transaction) {
            $transaction->updateAccountBalance('add');
        });

        // When a transaction is updated, adjust the account balance
        static::updated(function ($transaction) {
            $transaction->updateAccountBalance('update');
        });

        // When a transaction is deleted, revert the account balance
        static::deleted(function ($transaction) {
            $transaction->updateAccountBalance('subtract');
        });
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Update the account balance based on the transaction
     *
     * @param  string  $operation  'add', 'subtract', or 'update'
     */
    public static function balanceImpact(string $type, float|int|string $amount, ?string $categoryCode = null): float
    {
        $amount = (float) $amount;

        if ($type === self::TYPE_INCOME || $categoryCode === self::CATEGORY_TRANSFER_INCOMING) {
            return $amount;
        }

        return -$amount;
    }

    public function getBalanceImpact(): float
    {
        return self::balanceImpact(
            $this->transaction_type,
            $this->amount,
            $this->category?->code
        );
    }

    public function updateAccountBalance($operation = 'add')
    {
        // Resolve by the current foreign key instead of a potentially stale loaded relation.
        $account = Account::find($this->account_id);

        if (! $account) {
            return;
        }

        $amountImpact = $this->getBalanceImpact();

        switch ($operation) {
            case 'add':
                $account->current_balance += $amountImpact;
                break;

            case 'subtract':
                $account->current_balance -= $amountImpact;
                break;

            case 'update':
                $originalAccountId = $this->getOriginal('account_id');
                $originalCategoryCode = Category::find($this->getOriginal('category_id'))?->code;
                $oldAmountImpact = self::balanceImpact(
                    $this->getOriginal('transaction_type'),
                    $this->getOriginal('amount'),
                    $originalCategoryCode
                );

                if ($originalAccountId != $this->account_id) {
                    $oldAccount = Account::find($originalAccountId);
                    if ($oldAccount) {
                        $oldAccount->current_balance -= $oldAmountImpact;
                        $oldAccount->save();
                    }

                    $account->current_balance += $amountImpact;
                } else {
                    $account->current_balance -= $oldAmountImpact;
                    $account->current_balance += $amountImpact;
                }
                break;
        }

        $account->save();
    }
}
