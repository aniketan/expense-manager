<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'bank_name',
        'account_number',
        'ifsc_code',
        'opening_balance',
        'current_balance',
        'credit_limit',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Account types as constants
    public const TYPE_SAVINGS = 'savings';
    public const TYPE_CURRENT = 'current';
    public const TYPE_CREDIT_CARD = 'credit_card';
    public const TYPE_CASH = 'cash';
    public const TYPE_INVESTMENT = 'investment';

    // Get all available account types
    public static function getTypes(): array
    {
        return [
            self::TYPE_SAVINGS => 'Savings',
            self::TYPE_CURRENT => 'Current',
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_CASH => 'Cash',
            self::TYPE_INVESTMENT => 'Investment',
        ];
    }

    // Scope for active accounts
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Get formatted balance
    public function getFormattedCurrentBalanceAttribute()
    {
        return number_format($this->current_balance, 2);
    }

    // Get formatted opening balance
    public function getFormattedOpeningBalanceAttribute()
    {
        return number_format($this->opening_balance, 2);
    }

    // Get formatted credit limit
    public function getFormattedCreditLimitAttribute()
    {
        return number_format($this->credit_limit, 2);
    }

    // Check if account is credit card type
    public function isCreditCard()
    {
        return $this->type === self::TYPE_CREDIT_CARD;
    }

    // Get account type label
    public function getTypeLabel()
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }
}
