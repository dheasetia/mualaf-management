<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Domain\Identity\Rbac\RoleCatalog;
use Database\Seeders\RbacSeeder;

it('uses only catalogued, well-formed permission codes', function (): void {
    expect(PermissionCatalog::CODES)->toEqual(array_values(array_unique(PermissionCatalog::CODES)));

    foreach (PermissionCatalog::CODES as $code) {
        expect($code)->toMatch('/^[a-z_]+(\.[a-z_]+){1,2}$/');
    }

    foreach (RoleCatalog::roles() as $role => $definition) {
        foreach ($definition['permissions'] as $permission) {
            expect(PermissionCatalog::exists($permission))->toBeTrue("{$role} uses unknown {$permission}");
        }
    }
});

it('keeps platform roles away from office data', function (): void {
    $officeModules = ['prospects', 'converts', 'identity', 'shahadah', 'followups', 'guidance', 'exams'];

    foreach (RoleCatalog::roles() as $role => $definition) {
        if ($definition['scope'] !== RoleScope::Platform) {
            continue;
        }

        foreach ($definition['permissions'] as $permission) {
            $allowed = $permission === 'converts.correct_origin';
            expect($allowed || ! in_array(PermissionCatalog::split($permission)['module'], $officeModules, true))
                ->toBeTrue("{$role} must not hold {$permission}");
        }
    }
});

it('syncs the catalog idempotently and flags MFA roles', function (): void {
    $this->seed(RbacSeeder::class);
    $this->seed(RbacSeeder::class);

    expect(Role::query()->count())->toBe(count(RoleCatalog::roles()))
        ->and(Permission::query()->count())->toBe(count(PermissionCatalog::CODES))
        ->and(Role::query()->where('code', 'super_admin')->sole()->requires_mfa)->toBeTrue()
        ->and(Role::query()->where('code', 'approver')->sole()->requires_mfa)->toBeTrue()
        ->and(Role::query()->where('code', 'dai')->sole()->requires_mfa)->toBeFalse()
        ->and(Role::query()->where('code', 'teacher')->sole()->requires_mfa)->toBeFalse();
});
