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
                        'ifsc_code' => 'INDB0000732',
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

        $path = base_path('tests/fixtures/statement_indus_sample.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $this->post('/statements/process', ['statement' => $file])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Statements/Review', false)
                ->where('suggestedAccount.id', $account->id)
                ->where('accountMatch', 'full_number')
                ->has('accountMatchNote')
            );
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
                ],
            ],
        ]);

        $response->assertSessionHasErrors('rows');
        $this->assertSame(0, Transaction::query()->count());
    }
}
