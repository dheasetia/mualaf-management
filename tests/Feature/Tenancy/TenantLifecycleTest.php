<?php

declare(strict_types=1);

use App\Domain\Platform\Models\City;
use App\Domain\Tenancy\Actions\ChangeTenantStatus;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Exceptions\InvalidTenantTransition;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('creates a draft tenant with default settings and a uuid', function (): void {
    $city = City::factory()->create();

    $tenant = app(CreateTenant::class)->handle([
        'code' => 'KHB',
        'name_ar' => 'المركز الإسلامي بالخبر',
        'name_en' => 'Islamic Center Khobar',
        'region_id' => $city->region_id,
        'city_id' => $city->id,
    ]);

    expect($tenant->refresh()->status)->toBe(TenantStatus::Draft)
        ->and($tenant->uuid)->toBeString()->toHaveLength(36)
        ->and($tenant->settings?->settings['numbering_prefix'])->toBe('KHB');
});

it('does not accept status through mass assignment', function (): void {
    $city = City::factory()->create();

    app(CreateTenant::class)->handle([
        'code' => 'KHB',
        'name_ar' => 'x',
        'name_en' => 'x',
        'region_id' => $city->region_id,
        'city_id' => $city->id,
        'status' => 'active',
    ]);
})->throws(MassAssignmentException::class);

it('activates and suspends a tenant through allowed transitions', function (): void {
    $tenant = Tenant::factory()->create();
    $action = app(ChangeTenantStatus::class);

    $active = $action->handle($tenant, TenantStatus::Active);
    expect($active->status)->toBe(TenantStatus::Active)
        ->and($active->activated_at)->not->toBeNull();

    $suspended = $action->handle($active, TenantStatus::Suspended);
    expect($suspended->status)->toBe(TenantStatus::Suspended)
        ->and($suspended->suspended_at)->not->toBeNull();
});

it('rejects a transition the state machine does not allow', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Archived]);

    app(ChangeTenantStatus::class)->handle($tenant, TenantStatus::Active);
})->throws(InvalidTenantTransition::class);
