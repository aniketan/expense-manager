<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokenApplicationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_transactions_api_returns_not_found(): void
    {
        $this->getJson('/api/transactions')->assertNotFound();
    }

    public function test_unused_json_endpoints_are_removed(): void
    {
        foreach ([
            '/api/accounts',
            '/api/categories',
            '/api/categories/parents',
            '/api/categories/with-totals',
            '/api/budgets/summary',
            '/api/stats/dashboard',
            '/api/stats/today',
        ] as $uri) {
            $this->getJson($uri)->assertNotFound();
        }
    }

    public function test_dashboard_redirects_to_homepage(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_dashboard_analytics_remains_available(): void
    {
        $this->get('/dashboard/analytics')->assertOk();
    }
}
