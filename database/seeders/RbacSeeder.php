<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\PermissionCatalog;
use App\Domain\Identity\Rbac\RoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Syncs PermissionCatalog and RoleCatalog into the database. Idempotent: safe to
 * run on every deploy. Removes catalog-free permissions from system roles.
 */
final class RbacSeeder extends Seeder
{
    public function run(Authorizer $authorizer): void
    {
        DB::transaction(function (): void {
            $permissionIds = [];

            foreach (PermissionCatalog::CODES as $code) {
                $permissionIds[$code] = Permission::query()->updateOrCreate(
                    ['code' => $code],
                    PermissionCatalog::split($code),
                )->id;
            }

            Permission::query()->whereNotIn('code', PermissionCatalog::CODES)->delete();

            foreach (RoleCatalog::roles() as $code => $definition) {
                $permissions = array_values(array_unique($definition['permissions']));

                $role = Role::query()->updateOrCreate(['code' => $code], [
                    'name_ar' => $definition['name_ar'],
                    'name_en' => $definition['name_en'],
                    'scope_type' => $definition['scope'],
                    'is_system' => true,
                ]);

                $role->permissions()->sync(array_map(
                    fn (string $permission): int => $permissionIds[$permission],
                    $permissions,
                ));
            }
        });

        $authorizer->flush();
    }
}
