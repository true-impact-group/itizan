<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\CreatesFixtures;

abstract class TestCase extends BaseTestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Views reference built assets; tests do not need the Vite manifest.
        $this->withoutVite();
    }
}
