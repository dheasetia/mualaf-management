<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Identity\Rbac\RoleCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates or changes a custom role. System roles come from RoleCatalog and are
 * never changed here. A custom role holds only permissions some system role of
 * the same scope holds; its scope is fixed once created.
 */
final readonly class SaveCustomRole
{
    public function __construct(private Authorizer $authorizer, private AuditLogger $audit) {}

    /**
     * @param  array{name_ar: string, name_en: string, scope_type?: RoleScope|string, description?: string|null, permissions: list<string>}  $data
     */
    public function handle(?Role $role, array $data, User $savedBy): Role
    {
        if ($role?->is_system) {
            throw IdentityRuleViolation::systemRoleLocked();
        }

        $scope = $role?->scope_type ?? ($data['scope_type'] instanceof RoleScope ? $data['scope_type'] : RoleScope::from((string) $data['scope_type']));
        $permissions = array_values(array_unique($data['permissions']));

        if ($permissions === []) {
            throw IdentityRuleViolation::permissionsRequired();
        }

        $allowed = RoleCatalog::assignablePermissions($scope);

        foreach ($permissions as $permission) {
            if (! in_array($permission, $allowed, true)) {
                throw IdentityRuleViolation::permissionNotAllowed($permission);
            }
        }

        $isNew = $role === null;
        $role ??= new Role(['code' => $this->codeFor($data['name_en']), 'scope_type' => $scope, 'is_system' => false]);
        $oldPermissions = $isNew ? [] : $role->permissions()->orderBy('code')->pluck('code')->all();

        $role->fill([
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim($data['name_en']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
        ]);

        DB::transaction(function () use ($role, $permissions, $isNew, $oldPermissions, $savedBy): void {
            $role->save();
            $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id')->all());

            $this->audit->record(
                $isNew ? 'roles.created' : 'roles.updated',
                $role,
                oldValues: $isNew ? [] : ['permissions' => $oldPermissions],
                newValues: ['code' => $role->code, 'scope' => $role->scope_type->value, 'permissions' => $permissions],
                actor: $savedBy,
            );
        });

        $this->authorizer->flush();

        return $role;
    }

    private function codeFor(string $nameEn): string
    {
        $code = Str::limit('custom_'.Str::slug($nameEn, '_'), 60, '');

        if ($code === 'custom_' || Role::query()->where('code', $code)->exists()) {
            throw IdentityRuleViolation::roleCodeTaken();
        }

        return $code;
    }
}
