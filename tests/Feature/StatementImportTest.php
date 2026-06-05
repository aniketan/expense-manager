<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class StatementImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_page_loads(): void
    {
        $this->get('/statements/upload')->assertOk();
    }

    public function test_process_inertia_includes_suggested_account_when_number_matches(): void
    {
        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'account_info' => [
                        'account_holder_name' => 'Sample Holder',
                        'bank_name' => 'Sample Bank',
                        'account_number' => '100036608561',
                        'ifsc_code' => 'TEST0001234',
                        'account_type' => 'savings',
                        'statement_period' => 'Mar 2026',
                    ],
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $account = Account::factory()->create([
            'is_active' => true,
            'account_number' => '100036608561',
        ]);

        $path = base_path('tests/fixtures/statement_demo_sample.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $this->post('/statements/process', ['statement' => $file])
            ->assertRedirect(route('statements.review'));

        $this->get(route('statements.review'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Statements/Review', false)
                ->where('suggestedAccount.id', $account->id)
                ->where('accountMatch', 'full_number')
                ->has('accountMatchNote')
                ->has('reconcilePeriod')
                ->has('reconcileSummary')
            );
    }

    public function test_review_loads_multi_month_snapshot_from_session(): void
    {
        $snapshot = [
            'parsedData' => [
                'account_info' => [],
                'transactions' => [
                    [
                        'date' => '2026-01-15',
                        'description' => 'Jan row',
                        'amount' => 10,
                        'type' => 'expense',
                        'reconcile_status' => 'missing_in_db',
                    ],
                    [
                        'date' => '2026-02-15',
                        'description' => 'Feb row',
                        'amount' => 20,
                        'type' => 'expense',
                        'reconcile_status' => 'missing_in_db',
                    ],
                ],
            ],
            'reconcilePeriod' => ['start' => '2026-01-15', 'end' => '2026-02-15'],
            'reconcileSummary' => [
                'missing_in_db' => 2,
                'matched_complete' => 0,
                'matched_needs_enrichment' => 0,
            ],
            'reconcileDebug' => false,
            'suggestedAccount' => null,
            'accountMatch' => null,
            'accountMatchNote' => null,
            'suggested_account_id' => null,
        ];

        $this->withSession(['statement_review_snapshot' => $snapshot])
            ->get(route('statements.review'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Statements/Review', false)
                ->has('parsedData.transactions', 2)
            );
    }

    public function test_import_validation_redirects_to_review_with_field_errors_when_snapshot_present(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create([
            'is_active' => true,
            'current_balance' => 10000,
            'opening_balance' => 10000,
        ]);

        $snapshot = [
            'parsedData' => [
                'account_info' => [],
                'transactions' => [
                    [
                        'date' => '2026-01-15',
                        'description' => 'NEFT TEST',
                        'amount' => 250.50,
                        'type' => 'expense',
                        'reconcile_status' => 'missing_in_db',
                    ],
                ],
            ],
            'reconcilePeriod' => ['start' => '2026-01-15', 'end' => '2026-01-15'],
            'reconcileSummary' => [
                'missing_in_db' => 1,
                'matched_complete' => 0,
                'matched_needs_enrichment' => 0,
            ],
            'reconcileDebug' => false,
            'suggestedAccount' => $account->only(['id', 'name', 'account_number', 'bank_name']),
            'accountMatch' => 'full_number',
            'accountMatchNote' => null,
            'suggested_account_id' => $account->id,
        ];

        $missingId = max($child->id + 1000, 999_999);

        $this->withSession(['statement_review_snapshot' => $snapshot])
            ->from(route('statements.review'))
            ->post('/statements/import', [
                'rows' => [
                    [
                        'date' => '2026-01-15',
                        'description' => 'NEFT TEST',
                        'amount' => 250.50,
                        'type' => 'expense',
                        'account_id' => $account->id,
                        'category_id' => $missingId,
                        'reference' => null,
                        'review_row_index' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('statements.review'))
            ->assertSessionHasErrors('rows.0.category_id');
    }

    public function test_import_creates_transactions_and_updates_balance(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create([
            'is_active' => true,
            'current_balance' => 10000,
            'opening_balance' => 10000,
        ]);

        $response = $this->post('/statements/import', [
            'rows' => [
                [
                    'date' => '2026-01-15',
                    'description' => 'NEFT TEST',
                    'amount' => 250.50,
                    'type' => 'expense',
                    'account_id' => $account->id,
                    'category_id' => $child->id,
                    'reference' => 'UTR123',
                    'review_row_index' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'category_id' => $child->id,
            'amount' => '250.50',
            'transaction_type' => 'expense',
            'reference_number' => 'UTR123',
        ]);

        $account->refresh();
        $this->assertEqualsWithDelta(9749.50, (float) $account->current_balance, 0.01);
    }

    public function test_import_rejects_inactive_account(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create(['is_active' => false]);

        $response = $this->from('/statements/upload')->post('/statements/import', [
            'rows' => [
                [
                    'date' => '2026-01-15',
                    'description' => 'Test',
                    'amount' => 10,
                    'type' => 'expense',
                    'account_id' => $account->id,
                    'category_id' => $child->id,
                    'review_row_index' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('statements.upload'));
        $response->assertSessionHasErrors('rows');
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_import_accepts_income_row_when_income_root_code_is_lowercase_like_seeder(): void
    {
        $incomeRoot = Category::factory()->parent()->create(['code' => 'income', 'is_active' => true]);
        $incomeLeaf = Category::factory()->create(['parent_id' => $incomeRoot->id, 'is_active' => true]);
        $account = Account::factory()->create([
            'is_active' => true,
            'current_balance' => 10000,
            'opening_balance' => 10000,
        ]);

        $response = $this->post('/statements/import', [
            'rows' => [
                [
                    'date' => '2026-01-15',
                    'description' => 'Salary credit',
                    'amount' => 5000,
                    'type' => 'income',
                    'account_id' => $account->id,
                    'category_id' => $incomeLeaf->id,
                    'reference' => null,
                    'review_row_index' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'category_id' => $incomeLeaf->id,
            'transaction_type' => 'income',
            'amount' => '5000.00',
        ]);
    }

    public function test_import_rejects_two_identical_rows_in_one_request(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create([
            'is_active' => true,
            'current_balance' => 10000,
            'opening_balance' => 10000,
        ]);

        $row = [
            'date' => '2026-01-15',
            'description' => 'Dup test payment',
            'amount' => 99.5,
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $child->id,
            'reference' => null,
            'review_row_index' => 0,
        ];

        $this->from(route('statements.review'))
            ->post('/statements/import', [
                'rows' => [
                    $row,
                    array_replace($row, ['review_row_index' => 1]),
                ],
            ])
            ->assertSessionHasErrors([
                'rows.0.description',
                'rows.0.amount',
                'rows.1.description',
                'rows.1.amount',
            ]);

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_import_rejects_row_matching_existing_ledger_transaction(): void
    {
        $parent = Category::factory()->parent()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $account = Account::factory()->create([
            'is_active' => true,
            'current_balance' => 10000,
            'opening_balance' => 10000,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $child->id,
            'transaction_type' => 'expense',
            'amount' => 42.25,
            'description' => 'Existing   UPI txn',
            'transaction_date' => '2026-02-01',
            'payment_method' => 'Bank Transfer',
            'reference_number' => 'REF001',
        ]);

        $this->from(route('statements.review'))
            ->post('/statements/import', [
                'rows' => [
                    [
                        'date' => '2026-02-01',
                        'description' => 'existing upi txn',
                        'amount' => 42.25,
                        'type' => 'expense',
                        'account_id' => $account->id,
                        'category_id' => $child->id,
                        'reference' => 'ref001',
                        'review_row_index' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors(['rows.0.description', 'rows.0.amount']);

        $this->assertSame(1, Transaction::query()->count());
    }
}
