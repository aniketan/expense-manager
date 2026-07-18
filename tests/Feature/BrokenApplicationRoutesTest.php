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

    public function test_dashboard_redirects_to_homepage(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_dashboard_analytics_remains_available(): void
    {
        $this->get('/dashboard/analytics')->assertOk();
    }
}
