<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\UserRoleAssignment;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Roles\Pages\ListRoles;
use App\Filament\Center\Resources\Staff\Pages\ManageStaff;
use App\Filament\Central\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->admin = grantRole(memberOf($this->tenant), 'center_admin', $this->tenant);
});

function roleId(string $code): int
{
    return Role::query()->where('code', $code)->sole()->id;
}

function activeRoleCodes(User $user, ?Tenant $tenant): array
{
    return UserRoleAssignment::query()->where('user_id', $user->id)->where('tenant_id', $tenant?->id)->whereNull('revoked_at')
        ->with('role')->get()->map(fn (UserRoleAssignment $assignment): string => $assignment->role->code)->sort()->values()->all();
}

it('creates center staff with a password and office roles', function (): void {
    inCenterPanel($this->tenant, $this->admin);

    Livewire::test(ManageStaff::class)
        ->callAction('create', [
            'name' => 'Aisha Rahman', 'email' => 'Aisha@Example.org',
            'password' => 'first-password-42', 'password_confirmation' => 'first-password-42',
            'roles' => [roleId('dai')],
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('email', 'aisha@example.org')->sole();

    expect(Hash::check('first-password-42', $user->password))->toBeTrue()
        ->and($user->accessibleTenants()->pluck('code')->all())->toBe(['DMM'])
        ->and(activeRoleCodes($user, $this->tenant))->toBe(['dai'])
        ->and(AuditLog::query()->where('action', 'users.created')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('refuses a weak password or a taken email, and adds existing accounts by email instead', function (): void {
    inCenterPanel($this->tenant, $this->admin);
    $elsewhere = memberOf(Tenant::factory()->active()->create(['code' => 'JED']));

    Livewire::test(ManageStaff::class)
        ->callAction('create', ['name' => 'Weak', 'email' => 'weak@example.org', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertHasActionErrors(['password']);
    Livewire::test(ManageStaff::class)
        ->callAction('create', ['name' => 'Copy', 'email' => $elsewhere->email, 'password' => 'first-password-42', 'password_confirmation' => 'first-password-42'])
        ->assertHasActionErrors(['email']);
    Livewire::test(ManageStaff::class)
        ->callAction('addExisting', ['email' => strtoupper($elsewhere->email), 'roles' => [roleId('teacher')]])
        ->assertHasNoActionErrors()
        ->callAction('addExisting', ['email' => $elsewhere->email])
        ->assertHasActionErrors(['email']);

    expect(activeRoleCodes($elsewhere, $this->tenant))->toBe(['teacher'])
        ->and($elsewhere->accessibleTenants()->pluck('code')->sort()->values()->all())->toBe(['DMM', 'JED']);
});

it('lets an office admin edit only accounts that belong to this office alone', function (): void {
    inCenterPanel($this->tenant, $this->admin);
    $local = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $shared = memberOf($this->tenant, Tenant::factory()->active()->create(['code' => 'JED']));
    $national = grantRole(memberOf($this->tenant), 'central_admin');

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('edit')->table($local), ['name' => 'Renamed', 'email' => $local->email, 'password' => 'second-password-42', 'password_confirmation' => 'second-password-42'])
        ->assertHasNoActionErrors()
        ->assertActionHidden(TestAction::make('edit')->table($shared))
        ->assertActionHidden(TestAction::make('edit')->table($national))
        ->assertActionHidden(TestAction::make('edit')->table($this->admin))
        ->assertActionHidden(TestAction::make('roles')->table($this->admin));

    expect($local->refresh()->name)->toBe('Renamed')
        ->and(Hash::check('second-password-42', $local->password))->toBeTrue();
});

it('changes office roles and asks for a reason when one is removed', function (): void {
    inCenterPanel($this->tenant, $this->admin);
    $staff = grantRole(memberOf($this->tenant), 'dai', $this->tenant);

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('roles')->table($staff), ['roles' => [roleId('teacher')]])
        ->assertHasActionErrors(['reason']);
    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('roles')->table($staff), ['roles' => [roleId('teacher')], 'reason' => 'Moved to teaching'])
        ->assertHasNoActionErrors();

    expect(activeRoleCodes($staff, $this->tenant))->toBe(['teacher'])
        ->and(UserRoleAssignment::query()->where('user_id', $staff->id)->whereNotNull('revoked_at')->sole()->revoke_reason)->toBe('Moved to teaching');
});

it('removes staff from the office without touching their other offices', function (): void {
    inCenterPanel($this->tenant, $this->admin);
    $jeddah = Tenant::factory()->active()->create(['code' => 'JED']);
    $staff = grantRole(grantRole(memberOf($this->tenant, $jeddah), 'dai', $this->tenant), 'dai', $jeddah);

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('removeFromOffice')->table($staff), ['reason' => 'Left the center'])
        ->assertHasNoActionErrors()
        ->assertCanNotSeeTableRecords([$staff]);

    expect(activeRoleCodes($staff, $this->tenant))->toBe([])
        ->and(activeRoleCodes($staff, $jeddah))->toBe(['dai'])
        ->and($staff->tenantMemberships()->where('tenant_id', $this->tenant->id)->sole()->status)->toBe(MembershipStatus::Ended)
        ->and($staff->refresh()->is_active)->toBeTrue();
});

it('shows staff and roles only to holders of users.view, with office roles only', function (): void {
    $this->actingAs(grantRole(memberOf($this->tenant), 'dai', $this->tenant))->get('/center/DMM/staff')->assertForbidden();

    inCenterPanel($this->tenant, $this->admin);
    Livewire::test(ListRoles::class)
        ->assertCanSeeTableRecords([Role::query()->where('code', 'dai')->sole()])
        ->assertCanNotSeeTableRecords([Role::query()->where('code', 'super_admin')->sole()]);
});

it('manages accounts, platform roles and center roles from Central', function (): void {
    $centralAdmin = grantRole(User::factory()->create(), 'central_admin');
    $this->actingAs($centralAdmin);
    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Omar Auditor', 'email' => 'omar@example.org',
            'password' => 'first-password-42', 'password_confirmation' => 'first-password-42',
            'roles' => [roleId('central_auditor')],
        ])
        ->assertHasNoActionErrors();

    $omar = User::query()->where('email', 'omar@example.org')->sole();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('officeRoles')->table($omar), ['tenant_id' => $this->tenant->id, 'roles' => [roleId('local_auditor')]])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('deactivate')->table($omar))
        ->assertActionHidden(TestAction::make('deactivate')->table($centralAdmin));

    expect(activeRoleCodes($omar, null))->toBe(['central_auditor'])
        ->and(activeRoleCodes($omar, $this->tenant))->toBe(['local_auditor'])
        ->and($omar->refresh()->is_active)->toBeFalse()
        ->and(app(Authorizer::class)->hasPlatformPermission($omar, 'audit.view_national'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'users.deactivated')->where('subject_id', $omar->id)->exists())->toBeTrue();
});
