<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\MissingTenantContext;
use App\Domain\Tenancy\Exceptions\TenantMismatch;
use App\Domain\Tenancy\Models\Tenant;
use Tests\Fixtures\Tenancy\TenantProbe;

beforeEach(function (): void {
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
});

it('fills tenant_id from the context on create', function (): void {
    $probe = inTenant($this->dammam, fn () => TenantProbe::query()->create(['name' => 'a']));

    expect($probe->tenant_id)->toBe($this->dammam->id);
})->group('tenant-isolation');

it('rejects creating a record for another tenant', function (): void {
    inTenant($this->dammam, fn () => TenantProbe::query()->create([
        'name' => 'a',
        'tenant_id' => $this->riyadh->id,
    ]));
})->throws(TenantMismatch::class)->group('tenant-isolation');

it('hides records of another tenant from queries and lookups', function (): void {
    $riyadhProbe = inTenant($this->riyadh, fn () => TenantProbe::query()->create(['name' => 'r']));
    inTenant($this->dammam, fn () => TenantProbe::query()->create(['name' => 'd']));

    inTenant($this->dammam, function () use ($riyadhProbe): void {
        expect(TenantProbe::query()->pluck('name')->all())->toBe(['d'])
            ->and(TenantProbe::query()->find($riyadhProbe->id))->toBeNull()
            ->and(TenantProbe::query()->whereKey($riyadhProbe->id)->update(['name' => 'x']))->toBe(0)
            ->and(TenantProbe::query()->whereKey($riyadhProbe->id)->delete())->toBe(0);
    });

    expect(inTenant($this->riyadh, fn () => TenantProbe::query()->sole()->name))->toBe('r');
})->group('tenant-isolation');

it('refuses to query tenant data without a context', function (): void {
    TenantProbe::query()->get();
})->throws(MissingTenantContext::class)->group('tenant-isolation');

it('refuses to move a record to another tenant', function (): void {
    $probe = inTenant($this->dammam, fn () => TenantProbe::query()->create(['name' => 'a']));

    inTenant($this->dammam, function () use ($probe): void {
        $probe->tenant_id = $this->riyadh->id;
        $probe->save();
    });
})->throws(TenantMismatch::class)->group('tenant-isolation');
