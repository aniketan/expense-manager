<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert on Inertia props, not compiled assets, so they
        // must not depend on `npm run build` having produced a Vite manifest.
        $this->withoutVite();
    }
}
