<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Tenancy\CreateTenantProbeJob;
use Tests\Fixtures\Tenancy\TenantProbe;

beforeEach(function (): void {
    config(['queue.default' => 'database']);
});

it('carries the tenant context into a queued job', function (): void {
    $tenant = Tenant::factory()->active()->create();

    // A statement (not an arrow fn) so the PendingDispatch is queued while the context is still set.
    inTenant($tenant, function (): void {
        CreateTenantProbeJob::dispatch('from-job');
    });
    expect(app(TenantContext::class)->has())->toBeFalse();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(inTenant($tenant, fn () => TenantProbe::query()->sole()->name))->toBe('from-job');
})->group('tenant-isolation');

it('fails a queued job that was dispatched without a tenant context', function (): void {
    CreateTenantProbeJob::dispatch('orphan');

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('tenant_probes')->count())->toBe(0);
})->group('tenant-isolation');
