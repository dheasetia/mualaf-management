<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Actions\RevokeRole;
use App\Domain\Identity\Exceptions\CannotChangeOwnRoles;
use App\Domain\Identity\Exceptions\InvalidRoleScope;
use App\Domain\Identity\Exceptions\RoleAlreadyAssigned;
use App\Domain\Identity\Exceptions\UserNotMemberOfTenant;
use App\Domain\Identity\Models\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
    $this->role = fn (string $code): Role => Role::query()->where('code', $code)->sole();
});

it('rejects an office role without a tenant and a platform role with one', function (string $role, bool $withTenant): void {
    app(AssignRole::class)->handle(memberOf($this->dammam), ($this->role)($role), $withTenant ? $this->dammam : null, null);
})->with([
    'office role, no tenant' => ['dai', false],
    'platform role, tenant' => ['super_admin', true],
])->throws(InvalidRoleScope::class);

it('rejects an office role for someone who is not a member of that tenant', function (): void {
    app(AssignRole::class)->handle(memberOf($this->riyadh), ($this->role)('dai'), $this->dammam, null);
})->throws(UserNotMemberOfTenant::class);

it('rejects a second active assignment of the same role', function (): void {
    $user = memberOf($this->dammam);
    grantRole($user, 'dai', $this->dammam);
    grantRole($user, 'dai', $this->dammam);
})->throws(RoleAlreadyAssigned::class);

it('lets a center admin assign office roles in their own tenant only', function (): void {
    $admin = grantRole(memberOf($this->dammam, $this->riyadh), 'center_admin', $this->dammam);
    $staff = memberOf($this->dammam, $this->riyadh);

    $assignment = app(AssignRole::class)->handle($staff, ($this->role)('dai'), $this->dammam, $admin);
    expect($assignment->assigned_by)->toBe($admin->id);

    expect(fn () => app(AssignRole::class)->handle($staff, ($this->role)('dai'), $this->riyadh, $admin))
        ->toThrow(AuthorizationException::class);
});

it('does not let a center admin create platform administrators', function (): void {
    $admin = grantRole(memberOf($this->dammam), 'center_admin', $this->dammam);

    app(AssignRole::class)->handle(User::factory()->create(), ($this->role)('super_admin'), null, $admin);
})->throws(AuthorizationException::class);

it('lets a central admin assign office roles in any tenant', function (): void {
    $centralAdmin = grantRole(User::factory()->create(), 'central_admin');

    $assignment = app(AssignRole::class)->handle(memberOf($this->riyadh), ($this->role)('center_admin'), $this->riyadh, $centralAdmin);

    expect($assignment->tenant_id)->toBe($this->riyadh->id);
});

it('does not let anyone change their own roles', function (): void {
    $admin = grantRole(memberOf($this->dammam), 'center_admin', $this->dammam);

    expect(fn () => app(AssignRole::class)->handle($admin, ($this->role)('approver'), $this->dammam, $admin))
        ->toThrow(CannotChangeOwnRoles::class)
        ->and(fn () => app(RevokeRole::class)->handle($admin->roleAssignments()->sole(), $admin, 'x'))
        ->toThrow(CannotChangeOwnRoles::class);
});

it('allows the same role again after revocation and keeps the history', function (): void {
    $centralAdmin = grantRole(User::factory()->create(), 'central_admin');
    $user = grantRole(memberOf($this->dammam), 'dai', $this->dammam);

    app(RevokeRole::class)->handle($user->roleAssignments()->sole(), $centralAdmin, 'Leave');
    grantRole($user, 'dai', $this->dammam);

    expect($user->roleAssignments()->count())->toBe(2)
        ->and($user->roleAssignments()->whereNull('revoked_at')->count())->toBe(1);
});

it('bootstraps the first super admin from the console', function (): void {
    $user = User::factory()->create(['email' => 'first.admin@muallaf.test']);

    $this->artisan('rbac:grant-super-admin', ['email' => 'first.admin@muallaf.test'])->assertSuccessful();

    expect($user->hasPermission('users.assign_roles'))->toBeTrue();
});
