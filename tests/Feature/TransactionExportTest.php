<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_route_is_registered_before_show(): void
    {
        $routes = collect(Route::getRoutes()->getRoutesByMethod()['GET'] ?? []);
        $uris = $routes->map(fn ($r) => $r->uri())->values()->all();
        $exportIndex = array_search('transactions/export', $uris, true);
        $showPatternIndex = array_search('transactions/{transaction}', $uris, true);

        $this->assertNotFalse($exportIndex, 'transactions/export route missing');
        $this->assertNotFalse($showPatternIndex, 'transactions/{transaction} route missing');
        $this->assertLessThan(
            $showPatternIndex,
            $exportIndex,
            'transactions/export must be registered before transactions/{transaction}'
        );
    }

    public function test_export_returns_csv_with_filtered_rows(): void
    {
        $parent = Category::factory()->parent()->create(['name' => 'Living']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Food']);
        $accountA = Account::factory()->create(['name' => 'Account A']);
        $accountB = Account::factory()->create(['name' => 'Account B']);

        Transaction::withoutEvents(function () use ($accountA, $accountB, $child) {
            Transaction::create([
                'account_id' => $accountA->id,
                'category_id' => $child->id,
                'transaction_type' => 'income',
                'amount' => 100,
                'transaction_date' => '2025-06-15',
                'payment_method' => 'UPI',
                'description' => 'Monthly pay',
                'reference_number' => 'REF-INC',
                'tags' => 'payroll',
            ]);
            Transaction::create([
                'account_id' => $accountA->id,
                'category_id' => $child->id,
                'transaction_type' => 'expense',
                'amount' => 40,
                'transaction_date' => '2025-06-16',
                'payment_method' => 'UPI',
                'description' => 'Groceries',
                'reference_number' => 'REF-EXP',
            ]);
            Transaction::create([
                'account_id' => $accountA->id,
                'category_id' => $child->id,
                'transaction_type' => 'transfer',
                'amount' => 25,
                'transaction_date' => '2025-06-17',
                'payment_method' => 'Bank Transfer',
                'description' => 'Xfer out',
            ]);
            Transaction::create([
                'account_id' => $accountB->id,
                'category_id' => $child->id,
                'transaction_type' => 'expense',
                'amount' => 99,
                'transaction_date' => '2025-06-18',
                'payment_method' => 'Cash',
                'description' => 'Other account',
            ]);
        });

        $query = http_build_query([
            'account' => $accountA->id,
            'date_from' => '2025-06-01',
            'date_to' => '2025-06-30',
            'cash_flow' => 'debit',
        ]);

        $response = $this->get('/transactions/export?'.$query);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('Groceries', $body);
        $this->assertStringContainsString('Xfer out', $body);
        $this->assertStringContainsString('Account A', $body);
        $this->assertStringNotContainsString('Monthly pay', $body);
        $this->assertStringNotContainsString('REF-INC', $body);
        $this->assertStringNotContainsString('Other account', $body);

        $lines = array_values(array_filter(explode("\n", $body), fn ($l) => trim($l) !== ''));
        $this->assertGreaterThanOrEqual(3, count($lines));
    }

    public function test_export_rejects_invalid_date_range_with_json(): void
    {
        $response = $this->getJson('/transactions/export?date_from=2026-01-10&date_to=2026-01-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from']);
    }
}
