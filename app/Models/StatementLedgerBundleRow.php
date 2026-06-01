<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementLedgerBundleRow extends Model
{
    protected $fillable = [
        'bundle_id',
        'statement_sequence',
        'statement_date',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(StatementLedgerBundle::class, 'bundle_id');
    }
}
