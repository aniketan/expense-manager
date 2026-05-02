<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\StatementParserService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    public function uploadPage(): Response
    {
        return Inertia::render('Statements/Upload');
    }

    public function process(Request $request, StatementParserService $parser): Response
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

        $accounts = Account::query()->active()->orderBy('name')->get();
        $categories = Category::query()
            ->active()
            ->parent()
            ->with(['activeChildren' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Statements/Review', [
            'parsedData' => $parsed,
            'suggestedAccount' => $suggestion['account']?->only(['id', 'name', 'account_number', 'bank_name']),
            'accountMatch' => $suggestion['match'],
            'accountMatchNote' => $suggestion['note'],
            'accounts' => $accounts->map(fn (Account $a) => $a->only(['id', 'name', 'account_number', 'bank_name']))->values()->all(),
            'categories' => $categories->map(function (Category $c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'active_children' => $c->activeChildren->map(fn (Category $ch) => $ch->only(['id', 'name']))->values()->all(),
                ];
            })->values()->all(),
        ]);
    }

    public function importTransactions(Request $request)
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
        ]);

        $accountIds = collect($validated['rows'])->pluck('account_id')->unique()->all();
        $activeIds = Account::query()->active()->whereIn('id', $accountIds)->pluck('id')->all();
        if (count($activeIds) !== count($accountIds)) {
            return back()->withErrors(['rows' => 'All accounts must be active.'])->withInput();
        }

        $count = 0;

        $incomeRootId = Category::query()->whereNull('parent_id')->where('code', 'INCOME')->value('id');

        foreach ($validated['rows'] as $row) {
            $category = Category::query()->with('parent')->find($row['category_id']);
            if (! $category) {
                return back()->withErrors(['rows' => 'Invalid category for one or more rows.'])->withInput();
            }
            if ($row['type'] === 'income') {
                if (! $category->parent_id || (int) $category->parent_id !== (int) $incomeRootId) {
                    return back()->withErrors(['rows' => 'Income transactions must use an income subcategory.'])->withInput();
                }
            } elseif ($row['type'] === 'expense') {
                if (! $category->parent_id) {
                    return back()->withErrors(['rows' => 'Expense transactions must use a leaf category (subcategory).'])->withInput();
                }
                if ($category->parent && $category->parent->code === 'INCOME') {
                    return back()->withErrors(['rows' => 'Expense transactions cannot use an income category.'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($validated, &$count) {
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

        return redirect()->route('transactions.index')
            ->with('success', "{$count} transactions imported successfully!");
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
}
