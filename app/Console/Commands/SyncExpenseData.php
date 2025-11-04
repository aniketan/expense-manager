<?php

namespace App\Console\Commands;

use App\Services\ExpenseSyncService;
use Illuminate\Console\Command;
use Exception;

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
                          {--force : Skip confirmation prompt}';

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

        $this->info('🚀 Starting Expense Data Sync');
        $this->newLine();

        // Show configuration
        $this->displayConfiguration($dbPath, $dryRun);

        // Confirmation prompt (unless force is used)
        if (!$force && !$dryRun) {
            if (!$this->confirm('Do you want to proceed with the sync?')) {
                $this->info('Sync cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            // Initialize sync service
            $syncService = new ExpenseSyncService($dbPath);

            // Create progress bar
            $this->info('Initializing sync process...');

            // Run sync
            $stats = $syncService->sync($dryRun);

            // Display results
            $this->displayResults($stats, $dryRun);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Display configuration information
     */
    private function displayConfiguration(?string $dbPath, bool $dryRun): void
    {
        $actualDbPath = $dbPath ?: config('sync.external_db_path');

        $this->info('📋 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['External DB Path', $actualDbPath],
                ['File Exists', file_exists($actualDbPath) ? '✅ Yes' : '❌ No'],
                ['Mode', $dryRun ? '🔍 Dry Run (no changes)' : '💾 Live Sync'],
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
