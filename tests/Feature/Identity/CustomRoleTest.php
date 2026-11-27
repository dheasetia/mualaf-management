<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Actions\SaveCustomRole;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\RoleCatalog;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Roles\Pages\ListRoles;
use App\Filament\Center\Resources\Staff\Pages\ManageStaff;
use App\Filament\Central\Resources\Roles\Pages\ManageRoles;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->centralAdmin = grantRole(User::factory()->create(), 'central_admin');
    $this->actingAs($this->centralAdmin);
    Filament::setCurrentPanel(Filament::getPanel('central'));
});

/** @param  list<string>  $permissions */
function customRoleData(array $permissions, string $nameEn = 'Volunteer coordinator'): array
{
    return ['name_ar' => 'منسق المتطوعين', 'name_en' => $nameEn, 'scope_type' => RoleScope::Tenant->value, 'permissions' => $permissions];
}

it('creates a custom office role that grants its permissions once assigned', function (): void {
    Livewire::test(ManageRoles::class)
        ->callAction('create', customRoleData(['prospects.view', 'converts.view']))
        ->assertHasNoActionErrors();

    $role = Role::query()->where('code', 'custom_volunteer_coordinator')->sole();
    expect($role->is_system)->toBeFalse()
        ->and($role->permissions()->orderBy('code')->pluck('code')->all())->toBe(['converts.view', 'prospects.view'])
        ->and(AuditLog::query()->where('action', 'roles.created')->exists())->toBeTrue();

    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $staff = memberOf($tenant);
    inCenterPanel($tenant, grantRole(memberOf($tenant), 'center_admin', $tenant));

    Livewire::test(ManageStaff::class)
        ->callAction(TestAction::make('roles')->table($staff), ['roles' => [$role->id]])
        ->assertHasNoActionErrors();

    expect(app(Authorizer::class)->hasTenantPermission($staff, $tenant->id, 'prospects.view'))->toBeTrue()
        ->and(app(Authorizer::class)->hasTenantPermission($staff, $tenant->id, 'converts.update'))->toBeFalse();
});

it('keeps platform powers out of office roles', function (): void {
    expect(RoleCatalog::assignablePermissions(RoleScope::Tenant))->not->toContain('tenants.create')
        ->and(RoleCatalog::assignablePermissions(RoleScope::Platform))->toContain('tenants.create');

    Livewire::test(ManageRoles::class)
        ->callAction('create', customRoleData(['tenants.create']))
        ->assertHasActionErrors(['permissions.0']);

    expectRuleViolation(fn () => app(SaveCustomRole::class)->handle(null, customRoleData(['tenants.create']), $this->centralAdmin), 'permissions');

    expect(Role::query()->where('is_system', false)->exists())->toBeFalse();
});

it('keeps system roles read-only and custom roles deletable only until assigned', function (): void {
    $system = Role::query()->where('code', 'dai')->sole();
    Livewire::test(ManageRoles::class)
        ->callAction('create', customRoleData(['prospects.view']))
        ->callAction('create', customRoleData(['prospects.view'], 'Unused helper'));
    $assigned = Role::query()->where('code', 'custom_volunteer_coordinator')->sole();
    $unused = Role::query()->where('code', 'custom_unused_helper')->sole();
    $tenant = Tenant::factory()->active()->create();
    grantRole(memberOf($tenant), $assigned->code, $tenant);

    Livewire::test(ManageRoles::class)
        ->assertActionHidden(TestAction::make('edit')->table($system))
        ->assertActionHidden(TestAction::make('delete')->table($system))
        ->assertActionHidden(TestAction::make('delete')->table($assigned))
        ->callAction(TestAction::make('edit')->table($assigned), ['name_ar' => 'منسق', 'name_en' => 'Volunteer coordinator', 'permissions' => ['prospects.view', 'converts.view']])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('delete')->table($unused));

    expect($assigned->refresh()->permissions()->count())->toBe(2)
        ->and($assigned->scope_type)->toBe(RoleScope::Tenant)
        ->and(Role::query()->whereKey($unused->id)->exists())->toBeFalse();
});

it('leaves custom roles alone when the catalog is synced again', function (): void {
    Livewire::test(ManageRoles::class)->callAction('create', customRoleData(['prospects.view']));

    $this->seed(RbacSeeder::class);

    expect(Role::query()->where('code', 'custom_volunteer_coordinator')->sole()->permissions()->pluck('code')->all())->toBe(['prospects.view']);
});

it('lets offices view roles but not make them', function (): void {
    $tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $admin = inCenterPanel($tenant, grantRole(memberOf($tenant), 'center_admin', $tenant));

    expect($admin->can('create', Role::class))->toBeFalse();
    Livewire::test(ListRoles::class)->assertActionDoesNotExist('create');
});
