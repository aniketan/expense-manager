<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Transaction extends Model
{
    protected $table = 'transactions';
    use HasFactory;

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
        'amount',
        'description',
        'transaction_date',
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
     * @param string $operation 'add', 'subtract', or 'update'
     */
    public function updateAccountBalance($operation = 'add')
    {
        $account = $this->account;

        if (!$account) {
            return;
        }

        // Calculate the amount impact based on transaction type
        // For income: add to balance
        // For expense: subtract from balance
        $amountImpact = $this->transaction_type === 'income' ? $this->amount : -$this->amount;

        switch ($operation) {
            case 'add':
                // Adding a new transaction
                $account->current_balance += $amountImpact;
                break;

            case 'subtract':
                // Removing a transaction (revert its effect)
                $account->current_balance -= $amountImpact;
                break;

            case 'update':
                // Get the original values before the update
                $originalAccountId = $this->getOriginal('account_id');
                $originalAmount = $this->getOriginal('amount');
                $originalType = $this->getOriginal('transaction_type');

                // If the account changed, we need to update both accounts
                if ($originalAccountId != $this->account_id) {
                    // Revert the old account
                    $oldAccount = Account::find($originalAccountId);
                    if ($oldAccount) {
                        $oldAmountImpact = $originalType === 'income' ? $originalAmount : -$originalAmount;
                        $oldAccount->current_balance -= $oldAmountImpact;
                        $oldAccount->save();
                    }

                    // Add to new account
                    $account->current_balance += $amountImpact;
                } else {
                    // Same account, just adjust the difference
                    $oldAmountImpact = $originalType === 'income' ? $originalAmount : -$originalAmount;
                    $account->current_balance -= $oldAmountImpact; // Revert old impact
                    $account->current_balance += $amountImpact;     // Apply new impact
                }
                break;
        }

        $account->save();
    }

}
