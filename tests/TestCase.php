<?php

namespace Tests;

use App\Http\Middleware\RequireLogin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert on Inertia props, not compiled assets, so they
        // must not depend on `npm run build` having produced a Vite manifest.
        $this->withoutVite();

        // Most tests exercise app behavior as the logged-in user; login tests call actAsGuest().
        $this->withSession([RequireLogin::SESSION_KEY => true]);
    }

    protected function actAsGuest(): static
    {
        $this->app['session']->forget(RequireLogin::SESSION_KEY);

        return $this;
    }
}
