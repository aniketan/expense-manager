<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLedgerBundle;
use App\Models\Transaction;
use App\Services\StatementParserService;
use App\Services\StatementReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    private const REVIEW_SESSION_KEY = 'statement_review_snapshot';

    public function uploadPage(): Response
    {
        return Inertia::render('Statements/Upload');
    }

    public function review(Request $request): Response|RedirectResponse
    {
        $snapshot = $request->session()->get(self::REVIEW_SESSION_KEY);
        if (! is_array($snapshot)) {
            return redirect()->route('statements.upload')
                ->with('warning', 'Upload a statement first.');
        }

        return Inertia::render('Statements/Review', $this->reviewPagePropsFromSnapshot($snapshot));
    }

    public function process(Request $request, StatementParserService $parser, StatementReconciliationService $reconciliation): RedirectResponse
    {
        $request->validate([
            // Extension-based: many bank PDFs report as application/octet-stream and fail mimes:pdf.
            'statement' => [
                'required',
                'file',
                'max:12288',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['pdf', 'csv'], true)) {
                        $fail('The statement must be a PDF or CSV file.');
                    }
                },
            ],
        ], [
            'statement.required' => 'Please choose a PDF or CSV file.',
            'statement.max' => 'The statement may not be greater than 12MB.',
        ]);

        $parsed = $parser->parse($request->file('statement'));

        $suggestion = $this->resolveSuggestedAccount($parsed['account_info']['account_number'] ?? null);

        $reco = $reconciliation->analyze(
            $parsed['transactions'],
            $suggestion['account']?->id,
        );

        $parsedForReview = [
            'account_info' => $parsed['account_info'],
            'transactions' => $reco['transactions'],
        ];

        $snapshot = [
            'parsedData' => $parsedForReview,
            'reconcilePeriod' => $reco['period'],
            'reconcileSummary' => $reco['summary'],
            'reconcileDebug' => (bool) config('statement.reconcile_debug', false),
            'suggestedAccount' => $suggestion['account']?->only(['id', 'name', 'account_number', 'bank_name']),
            'accountMatch' => $suggestion['match'],
            'accountMatchNote' => $suggestion['note'],
            'suggested_account_id' => $suggestion['account']?->id,
        ];

        $request->session()->put(self::REVIEW_SESSION_KEY, $snapshot);

        return redirect()->route('statements.review');
    }

    public function importTransactions(Request $request, StatementReconciliationService $reconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.date' => 'required|date',
            'rows.*.description' => 'required|string|max:65535',
            'rows.*.amount' => 'required|numeric|min:0.01',
            'rows.*.type' => 'required|in:income,expense',
            'rows.*.account_id' => 'required|exists:accounts,id',
            'rows.*.category_id' => 'required|exists:categories,id',
            'rows.*.reference' => 'nullable|string|max:100',
            'rows.*.review_row_index' => 'required|integer|min:0',
        ]);

        $reviewRedirect = fn (): RedirectResponse => redirect()->route('statements.review');
        $failRedirect = fn (): RedirectResponse => $request->session()->has(self::REVIEW_SESSION_KEY)
            ? redirect()->route('statements.review')
            : redirect()->route('statements.upload');

        $accountIds = collect($validated['rows'])->pluck('account_id')->unique()->all();
        $activeIds = Account::query()->active()->whereIn('id', $accountIds)->pluck('id')->all();
        if (count($activeIds) !== count($accountIds)) {
            return $failRedirect()->withErrors(['rows' => 'All accounts must be active.'])->withInput();
        }

        $count = 0;

        foreach ($validated['rows'] as $row) {
            $category = Category::query()->with('parent')->find($row['category_id']);
            if (! $category) {
                return $failRedirect()->withErrors(['rows' => 'Invalid category for one or more rows.'])->withInput();
            }
            if ($row['type'] === 'income') {
                if (! $this->categoryIsAssignableIncomeCategory($category)) {
                    return $failRedirect()->withErrors(['rows' => 'Income transactions must use an income subcategory.'])->withInput();
                }
            } elseif ($row['type'] === 'expense') {
                if (! $category->parent_id) {
                    return $failRedirect()->withErrors(['rows' => 'Expense transactions must use a leaf category (subcategory).'])->withInput();
                }
                if ($this->categoryTreeRootIsIncome($category)) {
                    return $failRedirect()->withErrors(['rows' => 'Expense transactions cannot use an income category.'])->withInput();
                }
            }
        }

        $importDuplicateMessages = $this->validateImportDuplicates($validated['rows']);
        if ($importDuplicateMessages !== []) {
            return $failRedirect()->withErrors($importDuplicateMessages)->withInput();
        }

        DB::transaction(function () use ($validated, &$count): void {
            foreach ($validated['rows'] as $row) {
                Transaction::create([
                    'account_id' => $row['account_id'],
                    'category_id' => $row['category_id'],
                    'transaction_type' => $row['type'],
                    'amount' => $row['amount'],
                    'description' => $row['description'],
                    'transaction_date' => $row['date'],
                    'payment_method' => 'Bank Transfer',
                    'reference_number' => $row['reference'] ?? null,
                ]);
                $count++;
            }
        });

        $snapshot = $request->session()->get(self::REVIEW_SESSION_KEY);
        if (is_array($snapshot) && isset($snapshot['parsedData']['transactions']) && is_array($snapshot['parsedData']['transactions'])) {
            $indicesToRemove = collect($validated['rows'])
                ->pluck('review_row_index')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->sortDesc()
                ->values()
                ->all();

            $txns = $snapshot['parsedData']['transactions'];
            foreach ($indicesToRemove as $idx) {
                unset($txns[$idx]);
            }
            $txns = array_values($txns);

            if ($txns === []) {
                $request->session()->forget(self::REVIEW_SESSION_KEY);

                return redirect()->route('statements.upload')
                    ->with('success', "{$count} transaction(s) imported. All statement rows are processed.");
            }

            $reco = $reconciliation->analyze($txns, $snapshot['suggested_account_id'] ?? null);
            $snapshot['parsedData']['transactions'] = $reco['transactions'];
            $snapshot['reconcilePeriod'] = $reco['period'];
            $snapshot['reconcileSummary'] = $reco['summary'];
            $request->session()->put(self::REVIEW_SESSION_KEY, $snapshot);

            return $reviewRedirect()->with('success', "{$count} transaction(s) imported. Continue reviewing remaining rows.");
        }

        return redirect()->route('transactions.index')
            ->with('success', "{$count} transactions imported successfully!");
    }

    public function bundleLink(Request $request, StatementReconciliationService $reconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'ledger_transaction_id' => 'required|exists:transactions,id',
            'rows' => 'required|array|min:2',
            'rows.*.statement_sequence' => 'nullable|integer',
            'rows.*.date' => 'required|date',
            'rows.*.amount' => 'required|numeric|min:0.01',
            'rows.*.type' => 'required|in:income,expense',
            'rows.*.description' => 'nullable|string|max:65535',
        ]);

        $failRedirect = $request->session()->has(self::REVIEW_SESSION_KEY)
            ? redirect()->route('statements.review')
            : redirect()->route('statements.upload');

        $account = Account::query()->active()->find((int) $validated['account_id']);
        if ($account === null) {
            return $failRedirect->withErrors(['account_id' => 'Account must be active.'])->withInput();
        }

        $ledger = Transaction::query()->findOrFail((int) $validated['ledger_transaction_id']);
        if ((int) $ledger->account_id !== (int) $validated['account_id']) {
            return $failRedirect->withErrors(['ledger_transaction_id' => 'That transaction belongs to a different account.'])->withInput();
        }

        foreach ($validated['rows'] as $row) {
            if ($row['type'] !== $ledger->transaction_type) {
                return $failRedirect->withErrors(['rows' => 'Each selected row must have the same type as the ledger transaction (income vs expense).'])->withInput();
            }
        }

        $sumRows = round(collect($validated['rows'])->sum(fn ($r) => (float) $r['amount']), 2);
        if (abs($sumRows - (float) $ledger->amount) > 0.02) {
            return $failRedirect->withErrors([
                'rows' => 'The sum of selected statement amounts ('.$sumRows.') must equal the ledger amount ('.$ledger->amount.').',
            ])->withInput();
        }

        DB::transaction(function () use ($validated): void {
            $bundle = StatementLedgerBundle::create([
                'account_id' => (int) $validated['account_id'],
                'ledger_transaction_id' => (int) $validated['ledger_transaction_id'],
            ]);

            foreach ($validated['rows'] as $row) {
                $bundle->rows()->create([
                    'statement_sequence' => $row['statement_sequence'] ?? null,
                    'statement_date' => $row['date'],
                    'amount' => $row['amount'],
                    'description' => $row['description'] ?? null,
                ]);
            }
        });

        $snapshot = $request->session()->get(self::REVIEW_SESSION_KEY);
        if (is_array($snapshot) && isset($snapshot['parsedData']['transactions']) && is_array($snapshot['parsedData']['transactions'])) {
            $reco = $reconciliation->analyze($snapshot['parsedData']['transactions'], $snapshot['suggested_account_id'] ?? null);
            $snapshot['parsedData']['transactions'] = $reco['transactions'];
            $snapshot['reconcilePeriod'] = $reco['period'];
            $snapshot['reconcileSummary'] = $reco['summary'];
            $request->session()->put(self::REVIEW_SESSION_KEY, $snapshot);

            return redirect()->route('statements.review')
                ->with('success', 'Split rows linked to the ledger transaction and reconciliation refreshed.');
        }

        return redirect()->route('statements.upload')
            ->with('success', 'Split statement rows linked to the ledger transaction. Upload the same statement again to see bundle matches.');
    }

    public function enrichTransactions(Request $request, StatementReconciliationService $reconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.transaction_id' => 'required|exists:transactions,id',
            'updates.*.description' => 'required|string|max:65535',
            'updates.*.reference' => 'nullable|string|max:100',
            'updates.*.transaction_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['updates'] as $u) {
                $txn = Transaction::query()->findOrFail((int) $u['transaction_id']);
                $reference = array_key_exists('reference', $u) ? $u['reference'] : $txn->reference_number;
                $payload = [
                    'description' => $u['description'],
                    'reference_number' => $reference,
                ];
                if (array_key_exists('transaction_date', $u) && $u['transaction_date'] !== null && $u['transaction_date'] !== '') {
                    $payload['transaction_date'] = $u['transaction_date'];
                }
                $txn->update($payload);
            }
        });

        $n = count($validated['updates']);

        $snapshot = $request->session()->get(self::REVIEW_SESSION_KEY);
        if (is_array($snapshot) && isset($snapshot['parsedData']['transactions']) && is_array($snapshot['parsedData']['transactions'])) {
            $reco = $reconciliation->analyze($snapshot['parsedData']['transactions'], $snapshot['suggested_account_id'] ?? null);
            $snapshot['parsedData']['transactions'] = $reco['transactions'];
            $snapshot['reconcilePeriod'] = $reco['period'];
            $snapshot['reconcileSummary'] = $reco['summary'];
            $request->session()->put(self::REVIEW_SESSION_KEY, $snapshot);

            return redirect()->route('statements.review')
                ->with('success', "{$n} transaction(s) updated and checked against the ledger.");
        }

        return redirect()->route('statements.upload')
            ->with('success', "{$n} transaction(s) updated from statement.");
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function reviewPagePropsFromSnapshot(array $snapshot): array
    {
        $accounts = Account::query()->active()->orderBy('name')->get();
        $categories = Category::query()
            ->active()
            ->parent()
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'parsedData' => $snapshot['parsedData'],
            'reconcilePeriod' => $snapshot['reconcilePeriod'],
            'reconcileSummary' => $snapshot['reconcileSummary'],
            'reconcileDebug' => $snapshot['reconcileDebug'] ?? false,
            'suggestedAccount' => $snapshot['suggestedAccount'],
            'accountMatch' => $snapshot['accountMatch'],
            'accountMatchNote' => $snapshot['accountMatchNote'],
            'accounts' => $accounts->map(fn (Account $a) => $a->only(['id', 'name', 'account_number', 'bank_name']))->values()->all(),
            'categories' => $categories->map(function (Category $c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'active_children' => $c->activeChildren->map(fn (Category $ch) => $ch->only(['id', 'name']))->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{account: ?Account, match: ?string, note: ?string}
     */
    private function resolveSuggestedAccount(mixed $extractedAccountNumber): array
    {
        $normalized = $this->normalizeAccountDigits(is_string($extractedAccountNumber) ? $extractedAccountNumber : null);
        if ($normalized === '') {
            return [
                'account' => null,
                'match' => null,
                'note' => null,
            ];
        }

        $accounts = Account::query()->active()->get();

        foreach ($accounts as $account) {
            $dbNorm = $this->normalizeAccountDigits($account->account_number);
            if ($dbNorm !== '' && $dbNorm === $normalized) {
                return [
                    'account' => $account,
                    'match' => 'full_number',
                    'note' => 'This account was auto-selected because the statement number matches your saved account number.',
                ];
            }
        }

        $lastFour = strlen($normalized) >= 4 ? substr($normalized, -4) : $normalized;
        $candidates = $accounts->filter(function (Account $account) use ($lastFour) {
            $dbNorm = $this->normalizeAccountDigits($account->account_number);

            return $dbNorm !== '' && str_ends_with($dbNorm, $lastFour);
        });

        if ($candidates->count() === 1) {
            $account = $candidates->first();

            return [
                'account' => $account,
                'match' => 'last_four',
                'note' => 'This account was auto-selected using the last four digits of the statement account number.',
            ];
        }

        if ($candidates->count() > 1) {
            return [
                'account' => null,
                'match' => null,
                'note' => 'Several accounts share these last four digits. Pick the correct account for each row (or update account numbers).',
            ];
        }

        return [
            'account' => null,
            'match' => null,
            'note' => 'No saved account matches this statement number. Choose an account manually or add the account in Accounts.',
        ];
    }

    private function normalizeAccountDigits(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits ?? '';
    }

    /**
     * Detect duplicate lines in the import batch and duplicates of existing ledger rows (same account,
     * date, type, amount, reference, and whitespace-normalized lowercase description).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function validateImportDuplicates(array $rows): array
    {
        $errors = [];
        $duplicateInBatchMessage = 'Duplicate in this import: same account, date, type, amount, reference, and normalized description.';
        $duplicateInLedgerMessage = 'Already in ledger: matching account, date, type, amount, reference, and description.';

        $buckets = [];
        foreach ($rows as $idx => $row) {
            $buckets[$this->importDedupFingerprint($row)][] = (int) $idx;
        }
        foreach ($buckets as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            foreach ($indices as $i) {
                $errors["rows.{$i}.description"] = $duplicateInBatchMessage;
                $errors["rows.{$i}.amount"] = $duplicateInBatchMessage;
            }
        }

        foreach ($rows as $idx => $row) {
            if (isset($errors["rows.{$idx}.description"])) {
                continue;
            }
            if (! $this->importRowMatchesExistingTransaction($row)) {
                continue;
            }
            $errors["rows.{$idx}.description"] = $duplicateInLedgerMessage;
            $errors["rows.{$idx}.amount"] = $duplicateInLedgerMessage;
        }

        return $errors;
    }

    /** @param  array<string, mixed>  $row */
    private function normalizedImportDescription(mixed $description): string
    {
        if (! is_string($description)) {
            return '';
        }

        return Str::squish(Str::lower($description));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function normalizedImportReference(mixed $reference): string
    {
        if ($reference === null) {
            return '';
        }

        return Str::squish(Str::lower((string) $reference));
    }

    /** @param  array<string, mixed>  $row */
    private function importDedupFingerprint(array $row): string
    {
        return implode("\n", [
            (string) $row['account_id'],
            (string) $row['date'],
            (string) $row['type'],
            sprintf('%.2f', round((float) $row['amount'], 2)),
            $this->normalizedImportReference($row['reference'] ?? null),
            $this->normalizedImportDescription($row['description']),
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private function importRowMatchesExistingTransaction(array $row): bool
    {
        $targetDesc = $this->normalizedImportDescription($row['description']);
        $targetRef = $this->normalizedImportReference($row['reference'] ?? null);
        $targetAmount = sprintf('%.2f', round((float) $row['amount'], 2));

        $candidates = Transaction::query()
            ->where('account_id', (int) $row['account_id'])
            ->whereDate('transaction_date', $row['date'])
            ->where('transaction_type', $row['type'])
            ->get(['description', 'amount', 'reference_number']);

        foreach ($candidates as $txn) {
            $amount = sprintf('%.2f', round((float) $txn->amount, 2));
            if ($amount !== $targetAmount) {
                continue;
            }
            if ($this->normalizedImportReference($txn->reference_number) !== $targetRef) {
                continue;
            }
            if ($this->normalizedImportDescription((string) ($txn->description ?? '')) !== $targetDesc) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function categoryTreeRoot(Category $category): Category
    {
        $current = $category;
        for ($i = 0; $i < 64 && $current->parent_id !== null; $i++) {
            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $current;
    }

    private function categoryTreeRootIsIncome(Category $category): bool
    {
        return strtoupper((string) ($this->categoryTreeRoot($category)->code ?? '')) === 'INCOME';
    }

    /** True when category is a child/descendant under the Income tree (not the bare Income root row). */
    private function categoryIsAssignableIncomeCategory(Category $category): bool
    {
        if ($category->parent_id === null) {
            return false;
        }

        return $this->categoryTreeRootIsIncome($category);
    }
}
