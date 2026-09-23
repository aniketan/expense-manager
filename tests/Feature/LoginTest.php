<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.login_password' => 'correct horse']);
        RateLimiter::clear('login:127.0.0.1');
        $this->actAsGuest();
    }

    public function test_guest_page_visit_redirects_to_login(): void
    {
        foreach (['/', '/transactions', '/accounts', '/statements/upload', '/transactions/export'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_guest_inertia_form_submit_redirects_to_login_and_saves_nothing(): void
    {
        $this->post('/accounts', ['code' => 'X1', 'name' => 'X', 'type' => 'cash'], ['X-Inertia' => 'true'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('accounts', ['code' => 'X1']);
    }

    public function test_guest_api_style_requests_get_401_and_change_nothing(): void
    {
        $this->getJson('/chat/history?session_id=abc')->assertUnauthorized();
        $this->post('/chat/stream', ['message' => 'hi', 'session_id' => 'abc'], ['Accept' => 'text/event-stream'])
            ->assertUnauthorized();
        $this->postJson('/ai/categorize', ['description' => 'x', 'type' => 'expense'])->assertUnauthorized();
        $this->post('/transactions/bulk-destroy', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login', false)
                ->where('passwordConfigured', true));
    }

    public function test_correct_password_logs_in_and_returns_to_intended_page(): void
    {
        $this->get('/budgets')->assertRedirect(route('login'));

        $this->post(route('login.store'), ['password' => 'correct horse'])
            ->assertRedirect('/budgets');

        $this->assertTrue(session(RequireLogin::SESSION_KEY));
        $this->get('/budgets')->assertOk();
    }

    public function test_bcrypt_hashed_password_is_accepted(): void
    {
        config(['auth.login_password' => Hash::make('hashed secret')]);

        $this->post(route('login.store'), ['password' => 'hashed secret'])->assertRedirect(route('home'));
        $this->assertTrue(session(RequireLogin::SESSION_KEY));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post(route('login.store'), ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertNull(session(RequireLogin::SESSION_KEY));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_unset_password_never_lets_anyone_in(): void
    {
        config(['auth.login_password' => '']);

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('passwordConfigured', false));
        $this->post(route('login.store'), ['password' => ''])->assertSessionHasErrors('password');
        $this->post(route('login.store'), ['password' => 'anything'])->assertSessionHasErrors('password');

        $this->assertNull(session(RequireLogin::SESSION_KEY));
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['password' => 'wrong']);
        }

        $this->post(route('login.store'), ['password' => 'correct horse'])
            ->assertSessionHasErrors(['password' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertNull(session(RequireLogin::SESSION_KEY));
    }

    public function test_logout_ends_the_session(): void
    {
        $this->post(route('login.store'), ['password' => 'correct horse']);
        $this->get('/')->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_health_check_stays_public(): void
    {
        $this->get('/up')->assertOk();
    }
}
