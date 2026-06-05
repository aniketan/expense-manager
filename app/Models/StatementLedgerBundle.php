<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatementLedgerBundle extends Model
{
    protected $fillable = [
        'account_id',
        'ledger_transaction_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'ledger_transaction_id');
    }

    /** @return HasMany<StatementLedgerBundleRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(StatementLedgerBundleRow::class, 'bundle_id');
    }
}
