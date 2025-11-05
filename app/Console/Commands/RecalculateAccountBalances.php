<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account;

class RecalculateAccountBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:recalculate-balances {--account= : Specific account ID to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate account balances from opening balance and all transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account');

        if ($accountId) {
            // Recalculate specific account
            $account = Account::find($accountId);

            if (!$account) {
                $this->error("Account with ID {$accountId} not found.");
                return 1;
            }

            $oldBalance = $account->current_balance;
            $newBalance = $account->recalculateBalance();

            $this->info("Account: {$account->name} (ID: {$account->id})");
            $this->info("Old Balance: ₹" . number_format($oldBalance, 2));
            $this->info("New Balance: ₹" . number_format($newBalance, 2));
            $this->info("Difference: ₹" . number_format($newBalance - $oldBalance, 2));
            $this->info("✅ Balance recalculated successfully!");
        } else {
            // Recalculate all accounts
            $accounts = Account::all();
            $this->info("Recalculating balances for {$accounts->count()} accounts...");

            $this->withProgressBar($accounts, function ($account) {
                $account->recalculateBalance();
            });

            $this->newLine(2);
            $this->info("✅ All account balances recalculated successfully!");
        }

        return 0;
    }
}
