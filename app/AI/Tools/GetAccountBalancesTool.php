<?php

namespace App\AI\Tools;

use App\Models\Account;
use Prism\Prism\Tool;

class GetAccountBalancesTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('get_account_balances')
            ->for('Fetch current balances for all accounts. Use when the user asks about their balance, how much money they have, credit card limit, or wants to compare accounts. Optional account_name filters to a specific account.')
            ->withStringParameter('account_name', 'Partial account name to filter (e.g. "HDFC", "Cash"). Leave empty to return all accounts.', false)
            ->using($this->execute(...));
    }

    public function execute(?string $account_name = null): string
    {
        try {
            $query = Account::where('is_active', true)->orderBy('name');

            if (!empty($account_name)) {
                $query->where('name', 'like', '%' . $account_name . '%');
            }

            $accounts = $query->get();

            if ($accounts->isEmpty()) {
                return json_encode([
                    'success' => false,
                    'error'   => 'No active accounts found' . ($account_name ? " matching \"{$account_name}\"" : '') . '.',
                ]);
            }

            $totalLiquid = 0;
            $result = $accounts->map(function (Account $acc) use (&$totalLiquid) {
                $row = [
                    'name'            => $acc->name,
                    'type'            => $acc->getTypeLabel(),
                    'bank_name'       => $acc->bank_name,
                    'current_balance' => (float) $acc->current_balance,
                ];

                if ($acc->isCreditCard()) {
                    $used = max(0, -1 * (float) $acc->current_balance); // balance goes negative as credit is used
                    $row['credit_limit']     = (float) $acc->credit_limit;
                    $row['credit_used']      = $used;
                    $row['credit_available'] = max(0, (float) $acc->credit_limit - $used);
                } else {
                    $totalLiquid += (float) $acc->current_balance;
                }

                return $row;
            })->values()->all();

            return json_encode([
                'success'             => true,
                'accounts'            => $result,
                'total_liquid_balance'=> round($totalLiquid, 2),
                'account_count'       => count($result),
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error'   => 'Failed to fetch balances: ' . $e->getMessage(),
            ]);
        }
    }
}
