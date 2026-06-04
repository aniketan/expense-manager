<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\ExpenseSyncService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExpenseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expense:sync
                          {--dry-run : Run without making changes to the database}
                          {--db-path= : Path to external database file}
                          {--force : Skip confirmation prompt}
                          {--fresh : Preview and delete transactions previously synced from external DB (reference_number EXT_*) before syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync expense data from external SQLite database to Laravel application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $dbPath = $this->option('db-path');
        $force = $this->option('force');
        $fresh = $this->option('fresh');

        $this->info('🚀 Starting Expense Data Sync');
        $this->newLine();

        // Show configuration
        $this->displayConfiguration($dbPath, $dryRun, $fresh);

        $freshTransactions = collect();

        if ($fresh) {
            $freshTransactions = $this->freshSyncTransactions();
            $this->displayFreshPreview($freshTransactions);
        }

        if ($fresh && ! $dryRun && $freshTransactions->isNotEmpty() && ! $force) {
            if (! $this->confirm("Delete {$freshTransactions->count()} EXT_* transaction(s) using model deletes, then continue sync?")) {
                $this->info('Fresh sync cancelled before deleting any transactions.');

                return Command::SUCCESS;
            }
        }

        if (! $fresh && ! $force && ! $dryRun) {
            if (! $this->confirm('Do you want to proceed with the sync?')) {
                $this->info('Sync cancelled.');

                return Command::SUCCESS;
            }
        }

        try {
            if ($fresh && ! $dryRun) {
                DB::beginTransaction();
                $deleted = $this->deleteFreshTransactions($freshTransactions);
                $this->info("Removed {$deleted} previously synced transaction(s) (reference EXT_*) via model deletes.");
                $this->newLine();
            }
            // Initialize sync service
            $syncService = $this->makeSyncService($dbPath);

            // Create progress bar
            $this->info('Initializing sync process...');

            // Run sync
            $stats = $syncService->sync($dryRun);

            // Display results
            $this->displayResults($stats, $dryRun);

            if ($fresh && ! $dryRun) {
                DB::commit();
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            if ($fresh && ! $dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('❌ Sync failed: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    protected function makeSyncService(?string $dbPath): ExpenseSyncService
    {
        return new ExpenseSyncService($dbPath);
    }

    private function freshSyncTransactions()
    {
        return Transaction::query()
            ->where('reference_number', 'like', 'EXT_%')
            ->orderBy('id')
            ->get(['id', 'account_id', 'transaction_date', 'transaction_type', 'amount', 'reference_number', 'description']);
    }

    private function displayFreshPreview($transactions): void
    {
        $count = $transactions->count();
        if ($count === 0) {
            $this->info('Fresh sync preview: no EXT_* transactions found for deletion.');
            $this->newLine();

            return;
        }
        $this->warn("Fresh sync preview: {$count} EXT_* transaction(s) will be deleted before syncing.");
        $this->line('Affected transaction IDs: '.$transactions->pluck('id')->implode(', '));
        $this->table(
            ['ID', 'Date', 'Type', 'Amount', 'Reference', 'Description'],
            $transactions->take(25)->map(fn (Transaction $transaction) => [
                $transaction->id,
                optional($transaction->transaction_date)->toDateString(),
                $transaction->transaction_type,
                $transaction->amount,
                $transaction->reference_number,
                str($transaction->description)->limit(60)->toString(),
            ])->all()
        );
        if ($count > 25) {
            $this->line('Preview limited to first 25 rows; full affected ID list is shown above.');
        }
        $this->newLine();
    }

    private function deleteFreshTransactions($transactions): int
    {
        $deleted = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Display configuration information
     */
    private function displayConfiguration(?string $dbPath, bool $dryRun, bool $fresh = false): void
    {
        $actualDbPath = $dbPath ?: config('sync.external_db_path');

        $this->info('📋 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['External DB Path', $actualDbPath],
                ['File Exists', file_exists($actualDbPath) ? '✅ Yes' : '❌ No'],
                ['Mode', $dryRun ? '🔍 Dry Run (no changes)' : '💾 Live Sync'],
                ['Fresh (purge EXT_*)', $fresh ? 'Yes' : 'No'],
                ['Skip Duplicates', config('sync.sync_options.skip_duplicates') ? 'Yes' : 'No'],
                ['Batch Size', config('sync.sync_options.batch_size')],
            ]
        );
        $this->newLine();
    }

    /**
     * Display sync results
     */
    private function displayResults(array $stats, bool $dryRun): void
    {
        $this->newLine();

        if ($dryRun) {
            $this->info('🔍 Dry Run Results:');
        } else {
            $this->info('✅ Sync Completed Successfully!');
        }

        $this->table(
            ['Item', 'Count'],
            [
                ['Categories Processed', $stats['categories_synced']],
                ['Accounts Processed', $stats['accounts_synced']],
                ['Transactions Processed', $stats['transactions_synced']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($stats['errors'] > 0) {
            $this->warn("⚠️  {$stats['errors']} errors occurred during sync. Check the logs for details.");
        }

        if ($dryRun) {
            $this->info('💡 Run without --dry-run to actually sync the data.');
        } else {
            $this->info('🎉 Data has been successfully synced to your database!');
        }
    }
}
