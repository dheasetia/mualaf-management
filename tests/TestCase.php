<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Called by RefreshDatabase before migrating: adds test-only tables.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $this->app->make('migrator')->path(__DIR__.'/Fixtures/migrations');
    }

    /**
     * Panels load the Vite-built theme; tests render pages without a build.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        $this->app?->make(TenantContext::class)->clear();

        parent::tearDown();
    }
}
