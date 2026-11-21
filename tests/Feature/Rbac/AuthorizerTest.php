<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Actions\RevokeRole;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Tenancy\Actions\ChangeTenantStatus;
use App\Domain\Tenancy\Actions\EndTenantMembership;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    seedRbac();
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
});

it('grants office permissions only inside the tenant where the role is held', function (): void {
    $admin = grantRole(memberOf($this->dammam, $this->riyadh), 'center_admin', $this->dammam);

    expect(inTenant($this->dammam, fn () => $admin->hasPermission('users.create')))->toBeTrue()
        ->and(inTenant($this->riyadh, fn () => $admin->hasPermission('users.create')))->toBeFalse()
        ->and($admin->hasPermission('users.create'))->toBeFalse();
});

it('grants platform permissions only without tenant context', function (): void {
    $superAdmin = grantRole(memberOf($this->dammam), 'super_admin');

    expect($superAdmin->hasPermission('tenants.create'))->toBeTrue()
        ->and(inTenant($this->dammam, fn () => $superAdmin->hasPermission('tenants.create')))->toBeFalse()
        ->and(inTenant($this->dammam, fn () => $superAdmin->hasPermission('converts.view')))->toBeFalse();
});

it('answers catalog abilities through the Gate and leaves others to policies', function (): void {
    $dai = grantRole(memberOf($this->dammam), 'dai', $this->dammam);

    inTenant($this->dammam, function () use ($dai): void {
        expect(Gate::forUser($dai)->allows('prospects.create'))->toBeTrue()
            ->and(Gate::forUser($dai)->allows('prospects.assign_dai'))->toBeFalse()
            ->and(Gate::forUser($dai)->allows('not-a-permission'))->toBeFalse();
    });
});

it('drops permissions immediately when the role is revoked', function (): void {
    $centralAdmin = grantRole(User::factory()->create(), 'central_admin');
    $dai = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    expect(inTenant($this->dammam, fn () => $dai->hasPermission('prospects.create')))->toBeTrue();

    app(RevokeRole::class)->handle($dai->roleAssignments()->sole(), $centralAdmin, 'Moved to another office');

    expect(inTenant($this->dammam, fn () => $dai->hasPermission('prospects.create')))->toBeFalse();
});

it('drops office permissions when membership ends, the tenant is suspended, or the user is deactivated', function (string $change): void {
    $dai = grantRole(memberOf($this->dammam), 'dai', $this->dammam);
    // Warm the cache to prove the change is not hidden by it.
    expect(inTenant($this->dammam, fn () => $dai->hasPermission('prospects.create')))->toBeTrue();

    match ($change) {
        'membership' => app(EndTenantMembership::class)->handle($dai->tenantMemberships()->sole()),
        'tenant' => app(ChangeTenantStatus::class)->handle($this->dammam, TenantStatus::Suspended),
        'user' => app(DeactivateUser::class)->handle($dai),
    };
    app(Authorizer::class)->flush();

    expect(inTenant($this->dammam, fn () => $dai->refresh()->hasPermission('prospects.create')))->toBeFalse();
})->with(['membership', 'tenant', 'user']);
