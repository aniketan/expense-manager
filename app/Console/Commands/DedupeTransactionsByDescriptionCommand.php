<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeTransactionsByDescriptionCommand extends Command
{
    protected $signature = 'transactions:dedupe-by-description
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Delete without the interactive confirmation prompt}
                            {--keep=oldest : Keep oldest (lowest id) or newest per duplicate group}';

    protected $description = 'Delete duplicate transactions only when account, date, type, amount, and trimmed description all match';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $keep = strtolower((string) $this->option('keep'));
        if (! in_array($keep, ['oldest', 'newest'], true)) {
            $this->error('Option --keep must be oldest or newest.');

            return Command::FAILURE;
        }
        $groups = $this->duplicateGroups($keep);
        if ($groups->isEmpty()) {
            $this->info('No duplicate transaction groups found.');

            return Command::SUCCESS;
        }
        $totalDelete = $groups->sum(fn (array $group) => $group['delete_ids']->count());
        $this->displayPreview($groups, $totalDelete);
        if ($dryRun) {
            $this->warn("[dry-run] Would delete {$totalDelete} duplicate row(s). Run without --dry-run and confirm, or pass --force, to apply.");

            return Command::SUCCESS;
        }
        if (! $force && ! $this->confirm("Delete {$totalDelete} duplicate transaction(s) using model deletes?")) {
            $this->info('Dedupe cancelled before deleting any transactions.');

            return Command::SUCCESS;
        }
        $deleted = 0;
        foreach ($groups as $group) {
            $transactions = Transaction::query()
                ->whereIn('id', $group['delete_ids']->all())
                ->orderBy('id')
                ->get();
            foreach ($transactions as $transaction) {
                if ($transaction->delete()) {
                    $deleted++;
                }
            }
        }
        $this->info("Deleted {$deleted} duplicate transaction(s). Account balances were updated via model events.");

        return Command::SUCCESS;
    }

    private function duplicateGroups(string $keep)
    {
        $trimExpr = $this->trimmedDescriptionExpression();

        return DB::table('transactions')
            ->whereNotNull('description')
            ->whereRaw("{$trimExpr} != ".$this->emptySqlLiteral())
            ->selectRaw("account_id, transaction_date, transaction_type, amount, {$trimExpr} as desc_key, COUNT(*) as row_count")
            ->groupBy('account_id', 'transaction_date', 'transaction_type', 'amount', DB::raw($trimExpr))
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('account_id')
            ->orderBy('transaction_date')
            ->orderBy('transaction_type')
            ->orderBy('amount')
            ->get()
            ->map(function (object $row) use ($keep, $trimExpr) {
                $ids = DB::table('transactions')
                    ->where('account_id', $row->account_id)
                    ->where('transaction_date', $row->transaction_date)
                    ->where('transaction_type', $row->transaction_type)
                    ->where('amount', $row->amount)
                    ->whereRaw("{$trimExpr} = ?", [$row->desc_key])
                    ->orderBy('id')
                    ->pluck('id')
                    ->values();
                if ($ids->count() < 2) {
                    return null;
                }
                $keepId = $keep === 'oldest' ? $ids->first() : $ids->last();
                $deleteIds = $ids->reject(fn (int $id) => $id === $keepId)->values();

                return [
                    'account_id' => $row->account_id,
                    'transaction_date' => $row->transaction_date,
                    'transaction_type' => $row->transaction_type,
                    'amount' => $row->amount,
                    'description' => $row->desc_key,
                    'ids' => $ids,
                    'keep_id' => $keepId,
                    'delete_ids' => $deleteIds,
                ];
            })
            ->filter()
            ->values();
    }

    private function displayPreview($groups, int $totalDelete): void
    {
        $this->warn("Duplicate preview: {$groups->count()} group(s), {$totalDelete} transaction(s) marked for deletion.");
        $this->line('Affected transaction IDs: '.$groups->flatMap(fn (array $group) => $group['delete_ids'])->implode(', '));
        $this->table(
            ['Account', 'Date', 'Type', 'Amount', 'Description', 'Keep ID', 'Delete IDs'],
            $groups->take(25)->map(fn (array $group) => [
                $group['account_id'],
                $group['transaction_date'],
                $group['transaction_type'],
                $group['amount'],
                str($group['description'])->limit(50)->toString(),
                $group['keep_id'],
                $group['delete_ids']->implode(', '),
            ])->all()
        );
        if ($groups->count() > 25) {
            $this->line('Preview limited to first 25 groups; full affected ID list is shown above.');
        }
    }

    private function trimmedDescriptionExpression(): string
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => 'TRIM(IFNULL(description, '.$this->emptySqlLiteral().'))',
            default => 'TRIM(COALESCE(description, '.$this->emptySqlLiteral().'))',
        };
    }

    private function emptySqlLiteral(): string
    {
        return chr(39).chr(39);
    }
}
