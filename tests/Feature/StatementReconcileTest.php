<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLedgerBundle;
use App\Models\Transaction;
use App\Services\StatementReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class StatementReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function prismResponse(array $accountInfo): TextResponse
    {
        return new TextResponse(
            steps: collect([]),
            text: json_encode(['account_info' => $accountInfo]),
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(0, 0),
            meta: new Meta('fake', 'fake'),
            messages: collect([]),
        );
    }

    public function test_process_flags_balance_chain_when_statement_balances_do_not_continue(): void
    {
        Prism::fake([
            $this->prismResponse([
                'account_holder_name' => null,
                'bank_name' => null,
                'account_number' => '111122223333',
                'ifsc_code' => null,
                'account_type' => null,
                'statement_period' => null,
            ]),
        ]);

        $path = base_path('tests/fixtures/statement_demo_broken_balance.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $this->post('/statements/process', ['statement' => $file])
            ->assertRedirect(route('statements.review'));

        $this->get(route('statements.review'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reconcileSummary.balance.chain_ok', false)
                ->where('reconcileSummary.balance.broken_at_statement_sequence', 2)
                ->where('reconcileSummary.balance.broken_at_detail.previous_statement_sequence', 1)
                ->where('reconcileSummary.balance.broken_at_detail.current_statement_sequence', 2)
                ->where('reconcileSummary.balance.broken_at_detail.current_date', '2026-03-22')
                ->where('reconcileSummary.balance.broken_at_detail.current_description', 'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000')
                ->where('reconcileSummary.balance.broken_at_detail.current_debit_amount', 624.29)
                ->where('reconcileSummary.balance.broken_at_detail.previous_balance_after', 252758.24)
                ->where('reconcileSummary.balance.broken_at_detail.expected_balance_after', 252133.95)
                ->where('reconcileSummary.balance.broken_at_detail.actual_balance_after', 252000)
                ->where('reconcileSummary.balance.broken_at_detail.difference', -133.95)
            );
    }

    public function test_process_reconcile_summary_counts_matches_and_missing_rows(): void
    {
        Prism::fake([
            $this->prismResponse([
                'account_holder_name' => 'Sample Holder',
                'bank_name' => 'Sample Bank',
                'account_number' => '100036608561',
                'ifsc_code' => 'TEST0001234',
                'account_type' => 'savings',
                'statement_period' => 'Mar 2026',
            ]),
        ]);

        $account = Account::factory()->create([
            'is_active' => true,
            'account_number' => '100036608561',
            'current_balance' => 250000,
            'opening_balance' => 250000,
        ]);

        $incomeRoot = Category::factory()->parent()->create(['code' => 'INCOME', 'is_active' => true]);
        $incomeChild = Category::factory()->create(['parent_id' => $incomeRoot->id, 'is_active' => true]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $incomeChild->id,
            'transaction_type' => 'income',
            'amount' => 23250,
            'description' => 'UPI/900000000001/CR/DEMO/TEST/demo@okbank/Mar',
            'transaction_date' => '2026-03-22',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 624.29,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-22',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $this->assertSame(
            2,
            Transaction::query()
                ->where('account_id', $account->id)
                ->whereDate('transaction_date', '>=', '2026-03-22')
                ->whereDate('transaction_date', '<=', '2026-03-22')
                ->count(),
        );

        $path = base_path('tests/fixtures/statement_demo_sample.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $this->post('/statements/process', ['statement' => $file])
            ->assertRedirect(route('statements.review'));

        $this->get(route('statements.review'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reconcileSummary.balance.chain_ok', true)
                ->where('reconcileSummary.matched_complete', 1)
                ->where('reconcileSummary.matched_needs_enrichment', 1)
                ->where('reconcileSummary.missing_in_db', 0)
                ->where('reconcilePeriod.start', '2026-03-22')
                ->where('reconcilePeriod.end', '2026-03-22')
            );
    }

    public function test_enrich_updates_existing_transactions(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $txn = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 624.29,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-22',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $response = $this->post('/statements/enrich', [
            'updates' => [
                [
                    'transaction_id' => $txn->id,
                    'description' => 'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
                    'reference' => '2',
                ],
            ],
        ]);

        $response->assertRedirect();
        $txn->refresh();
        $this->assertStringContainsString('DEMO CREDIT CARD', $txn->description);
        $this->assertSame('2', $txn->reference_number);
    }

    public function test_reconcile_fallback_matches_when_statement_date_skews_from_ledger(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 412,
            'description' => 'UPI/777777777777/SKEWTEST UNIQUE DESC SEGMENT',
            'transaction_date' => '2026-02-28',
            'payment_method' => 'Bank Transfer',
            'reference_number' => 'LEDGER-REF-1',
        ]);

        $svc = app(StatementReconciliationService::class);
        $parsed = [
            [
                'date' => '2026-03-01',
                'description' => 'UPI/777777777777/SKEWTEST UNIQUE DESC SEGMENT',
                'amount' => 412.0,
                'type' => 'expense',
                'reference' => null,
                'debit_amount' => 412.0,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, $account->id);

        $this->assertSame('matched_needs_enrichment', $result['transactions'][0]['reconcile_status']);
        $this->assertTrue($result['transactions'][0]['date_drift']);
        $this->assertSame('2026-02-28', $result['transactions'][0]['ledger_transaction_date']);
        $this->assertSame('UPI/777777777777/SKEWTEST UNIQUE DESC SEGMENT', $result['transactions'][0]['db_description_snapshot']);
        $this->assertSame('LEDGER-REF-1', $result['transactions'][0]['db_reference_snapshot']);
        $this->assertNotNull($result['transactions'][0]['existing_transaction_id']);
        $this->assertContains('statement_date_differs_from_ledger', $result['transactions'][0]['needs_detail_reasons']);
    }

    public function test_reconcile_fallback_does_not_match_when_secondary_fingerprint_is_ambiguous(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);
        $sameDesc = 'UPI/888888888888/AMBIGUOUS SKEWTEST';

        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'description' => $sameDesc,
            'transaction_date' => '2026-02-27',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'description' => $sameDesc,
            'transaction_date' => '2026-03-02',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $svc = app(StatementReconciliationService::class);
        $parsed = [
            [
                'date' => '2026-03-01',
                'description' => $sameDesc,
                'amount' => 100.0,
                'type' => 'expense',
                'reference' => null,
                'debit_amount' => 100.0,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, $account->id);

        $this->assertSame('missing_in_db', $result['transactions'][0]['reconcile_status']);
        $this->assertNull($result['transactions'][0]['existing_transaction_id']);
        $this->assertFalse($result['transactions'][0]['date_drift']);
        $this->assertSame([], $result['transactions'][0]['needs_detail_reasons']);
    }

    public function test_enrich_updates_transaction_date_when_provided(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $txn = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 624.29,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-22',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $response = $this->post('/statements/enrich', [
            'updates' => [
                [
                    'transaction_id' => $txn->id,
                    'description' => 'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
                    'reference' => '2',
                    'transaction_date' => '2026-03-23',
                ],
            ],
        ]);

        $response->assertRedirect();
        $txn->refresh();
        $this->assertSame('2026-03-23', $txn->transaction_date->format('Y-m-d'));
    }

    public function test_enrich_rechecks_review_snapshot_and_turns_needs_detail_into_in_ledger(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $txn = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 412,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-02-28',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $parsed = [
            [
                'date' => '2026-03-01',
                'description' => 'UPI/777777777777/SKEWTEST UNIQUE DESC SEGMENT',
                'amount' => 412.0,
                'type' => 'expense',
                'reference' => null,
                'debit_amount' => 412.0,
                'credit_amount' => 0.0,
            ],
        ];

        $svc = app(StatementReconciliationService::class);
        $before = $svc->analyze($parsed, $account->id);
        $this->assertSame('matched_needs_enrichment', $before['transactions'][0]['reconcile_status']);

        $response = $this->withSession([
            'statement_review_snapshot' => [
                'parsedData' => [
                    'account_info' => [],
                    'transactions' => $before['transactions'],
                ],
                'reconcilePeriod' => $before['period'],
                'reconcileSummary' => $before['summary'],
                'reconcileDebug' => false,
                'suggestedAccount' => $account->only(['id', 'name', 'account_number', 'bank_name']),
                'accountMatch' => 'full_number',
                'accountMatchNote' => null,
                'suggested_account_id' => $account->id,
            ],
        ])->post('/statements/enrich', [
            'updates' => [
                [
                    'transaction_id' => $txn->id,
                    'description' => 'UPI/777777777777/SKEWTEST UNIQUE DESC SEGMENT',
                    'reference' => null,
                    'transaction_date' => '2026-03-01',
                ],
            ],
        ]);

        $response->assertRedirect('/statements/review');
        $snapshot = session('statement_review_snapshot');
        $this->assertSame('matched_complete', $snapshot['parsedData']['transactions'][0]['reconcile_status']);
        $this->assertSame(1, $snapshot['reconcileSummary']['matched_complete']);
        $this->assertSame(0, $snapshot['reconcileSummary']['matched_needs_enrichment']);
    }

    public function test_thin_placeholder_match_picks_nearest_ledger_date_among_candidates(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $near = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-02-27',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);
        Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-05',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $svc = app(StatementReconciliationService::class);
        $parsed = [
            [
                'date' => '2026-03-01',
                'description' => 'UPI/600000000001/DR/ANY/YESB/foo',
                'amount' => 100.0,
                'type' => 'expense',
                'statement_sequence' => 1,
                'debit_amount' => 100.0,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, $account->id);

        $this->assertSame('matched_needs_enrichment', $result['transactions'][0]['reconcile_status']);
        $this->assertSame($near->id, $result['transactions'][0]['existing_transaction_id']);
        $this->assertContains('existing_description_thin', $result['transactions'][0]['needs_detail_reasons']);
        $this->assertContains('matched_by_amount_proximity', $result['transactions'][0]['needs_detail_reasons']);
        $this->assertContains('statement_date_differs_from_ledger', $result['transactions'][0]['needs_detail_reasons']);
    }

    public function test_saved_bundle_links_multiple_statement_rows_to_one_ledger_txn(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $ledger = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 30,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-01',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $bundle = StatementLedgerBundle::create([
            'account_id' => $account->id,
            'ledger_transaction_id' => $ledger->id,
        ]);
        $bundle->rows()->create([
            'statement_sequence' => 14,
            'statement_date' => '2026-03-02',
            'amount' => 20,
            'description' => null,
        ]);
        $bundle->rows()->create([
            'statement_sequence' => 15,
            'statement_date' => '2026-03-03',
            'amount' => 10,
            'description' => null,
        ]);

        $svc = app(StatementReconciliationService::class);
        $parsed = [
            [
                'statement_sequence' => 14,
                'date' => '2026-03-02',
                'description' => 'UPI/split-a',
                'amount' => 20.0,
                'type' => 'expense',
                'debit_amount' => 20.0,
                'credit_amount' => 0.0,
            ],
            [
                'statement_sequence' => 15,
                'date' => '2026-03-03',
                'description' => 'UPI/split-b',
                'amount' => 10.0,
                'type' => 'expense',
                'debit_amount' => 10.0,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, $account->id);

        $this->assertTrue($result['transactions'][0]['matched_via_bundle']);
        $this->assertTrue($result['transactions'][1]['matched_via_bundle']);
        $this->assertSame($ledger->id, $result['transactions'][0]['existing_transaction_id']);
        $this->assertSame($ledger->id, $result['transactions'][1]['existing_transaction_id']);
        $this->assertSame((int) $bundle->id, $result['transactions'][0]['bundle_id']);
        $this->assertContains('matched_via_split_bundle', $result['transactions'][0]['needs_detail_reasons']);
        $this->assertContains('matched_via_split_bundle', $result['transactions'][1]['needs_detail_reasons']);
    }

    public function test_needs_detail_rows_include_reasons_and_are_logged(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $txn = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 624.29,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-22',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        Log::spy();

        $svc = app(StatementReconciliationService::class);
        $parsed = [
            [
                'statement_sequence' => 2,
                'date' => '2026-03-22',
                'description' => 'DEMO CREDIT CARD PAYMENT/XXXXXXXXXXXX0000',
                'amount' => 624.29,
                'type' => 'expense',
                'reference' => '2',
                'debit_amount' => 624.29,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, $account->id);

        $row = $result['transactions'][0];
        $this->assertSame('matched_needs_enrichment', $row['reconcile_status']);
        $this->assertSame($txn->id, $row['existing_transaction_id']);
        $this->assertSame([
            'existing_description_thin',
            'matched_by_date_amount_bucket',
        ], $row['needs_detail_reasons']);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Statement reconciliation row needs detail'
                && $context['row_index'] === 0
                && $context['statement_sequence'] === 2
                && $context['account_id'] === $account->id
                && $context['statement_date'] === '2026-03-22'
                && $context['ledger_transaction_date'] === '2026-03-22'
                && $context['amount'] === 624.29
                && $context['type'] === 'expense'
                && $context['existing_transaction_id'] === $txn->id
                && $context['reasons'] === ['existing_description_thin', 'matched_by_date_amount_bucket']
                && $context['match_attempt_reason'] === 'matched_thin_date_bucket'
            );
    }

    public function test_bundle_link_route_creates_bundle_and_redirects(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $ledger = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 30,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-01',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $this->post('/statements/bundle-link', [
            'account_id' => $account->id,
            'ledger_transaction_id' => $ledger->id,
            'rows' => [
                [
                    'statement_sequence' => 14,
                    'date' => '2026-03-02',
                    'amount' => 20,
                    'type' => 'expense',
                    'description' => 'a',
                ],
                [
                    'statement_sequence' => 15,
                    'date' => '2026-03-03',
                    'amount' => 10,
                    'type' => 'expense',
                    'description' => 'b',
                ],
            ],
        ])->assertRedirect(route('statements.upload'));

        $this->assertDatabaseCount('statement_ledger_bundles', 1);
        $this->assertDatabaseCount('statement_ledger_bundle_rows', 2);
    }

    public function test_bundle_link_route_refreshes_existing_review_snapshot(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        $expParent = Category::factory()->parent()->create(['code' => 'FOOD', 'is_active' => true]);
        $expChild = Category::factory()->create(['parent_id' => $expParent->id, 'is_active' => true]);

        $ledger = Transaction::create([
            'account_id' => $account->id,
            'category_id' => $expChild->id,
            'transaction_type' => 'expense',
            'amount' => 30,
            'description' => 'Synced from external DB',
            'transaction_date' => '2026-03-01',
            'payment_method' => 'Bank Transfer',
            'reference_number' => null,
        ]);

        $parsed = [
            [
                'statement_sequence' => 14,
                'date' => '2026-03-02',
                'description' => 'UPI/split-a',
                'amount' => 20.0,
                'type' => 'expense',
                'debit_amount' => 20.0,
                'credit_amount' => 0.0,
                'reconcile_status' => 'missing_in_db',
            ],
            [
                'statement_sequence' => 15,
                'date' => '2026-03-03',
                'description' => 'UPI/split-b',
                'amount' => 10.0,
                'type' => 'expense',
                'debit_amount' => 10.0,
                'credit_amount' => 0.0,
                'reconcile_status' => 'missing_in_db',
            ],
        ];

        $response = $this->withSession([
            'statement_review_snapshot' => [
                'parsedData' => [
                    'account_info' => [],
                    'transactions' => $parsed,
                ],
                'reconcilePeriod' => ['start' => '2026-03-02', 'end' => '2026-03-03'],
                'reconcileSummary' => [
                    'missing_in_db' => 2,
                    'matched_complete' => 0,
                    'matched_needs_enrichment' => 0,
                ],
                'reconcileDebug' => false,
                'suggestedAccount' => $account->only(['id', 'name', 'account_number', 'bank_name']),
                'accountMatch' => 'full_number',
                'accountMatchNote' => null,
                'suggested_account_id' => $account->id,
            ],
        ])->post('/statements/bundle-link', [
            'account_id' => $account->id,
            'ledger_transaction_id' => $ledger->id,
            'rows' => [
                [
                    'statement_sequence' => 14,
                    'date' => '2026-03-02',
                    'amount' => 20,
                    'type' => 'expense',
                    'description' => 'UPI/split-a',
                ],
                [
                    'statement_sequence' => 15,
                    'date' => '2026-03-03',
                    'amount' => 10,
                    'type' => 'expense',
                    'description' => 'UPI/split-b',
                ],
            ],
        ]);

        $response->assertRedirect(route('statements.review'));
        $response->assertSessionHas('success', 'Split rows linked to the ledger transaction and reconciliation refreshed.');

        $snapshot = session('statement_review_snapshot');
        $this->assertSame(0, $snapshot['reconcileSummary']['missing_in_db']);
        $this->assertSame(2, $snapshot['reconcileSummary']['matched_needs_enrichment']);
        $this->assertTrue($snapshot['parsedData']['transactions'][0]['matched_via_bundle']);
        $this->assertTrue($snapshot['parsedData']['transactions'][1]['matched_via_bundle']);
    }

    public function test_reconcile_debug_adds_match_attempt_reason_when_enabled(): void
    {
        $account = Account::factory()->create(['is_active' => true, 'current_balance' => 10000, 'opening_balance' => 10000]);
        Config::set('statement.reconcile_debug', true);

        try {
            $svc = app(StatementReconciliationService::class);
            $parsed = [
                [
                    'date' => '2026-03-01',
                    'description' => 'NOWHERE IN DB ROW',
                    'amount' => 99.99,
                    'type' => 'expense',
                    'debit_amount' => 99.99,
                    'credit_amount' => 0.0,
                ],
            ];
            $result = $svc->analyze($parsed, $account->id);
            $this->assertArrayHasKey('match_attempt_reason', $result['transactions'][0]);
        } finally {
            Config::set('statement.reconcile_debug', false);
        }
    }
}
