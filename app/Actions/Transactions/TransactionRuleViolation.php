<?php

namespace App\Actions\Transactions;

use Illuminate\Validation\ValidationException;

/**
 * A transaction domain rule was broken. It extends ValidationException so web
 * requests redirect back with a field error, while AI and MCP callers read getMessage().
 */
class TransactionRuleViolation extends ValidationException
{
    public static function on(string $field, string $message): static
    {
        return static::withMessages([$field => $message]);
    }
}
