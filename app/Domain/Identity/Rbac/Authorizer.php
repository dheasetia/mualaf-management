<?php

declare(strict_types=1);

namespace App\Domain\Identity\Rbac;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves permissions from active role assignments (03-tenancy-authorization §3.5).
 *
 * - With tenant context: only office roles held in that tenant, and only while the
 *   user's membership and the tenant are active.
 * - Without tenant context: only platform roles.
 *
 * Results are cached for five minutes per user and scope; any assignment change
 * bumps a version number so stale entries are never read.
 */
final readonly class Authorizer
{
    private const string VERSION_KEY = 'rbac:version';

    private const int TTL_SECONDS = 300;

    public function __construct(private TenantContext $context) {}

    public function check(User $user, string $permission): bool
    {
        $tenantId = $this->context->id();

        return $tenantId === null
            ? $this->hasPlatformPermission($user, $permission)
            : $this->hasTenantPermission($user, $tenantId, $permission);
    }

    public function hasPlatformPermission(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user, null), true);
    }

    public function hasTenantPermission(User $user, int $tenantId, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user, $tenantId), true);
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user, ?int $tenantId): array
    {
        if (! $user->is_active) {
            return [];
        }

        $key = sprintf('rbac:v%d:user:%d:%s', $this->version(), $user->id, $tenantId === null ? 'platform' : 'tenant:'.$tenantId);

        /** @var list<string> */
        return Cache::remember($key, self::TTL_SECONDS, fn (): array => $this->activeAssignments($user->id, $tenantId)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_role_assignments.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->distinct()
            ->orderBy('permissions.code')
            ->pluck('permissions.code')
            ->map(fn (mixed $code): string => (string) $code)
            ->values()
            ->all());
    }

    public function hasAnyPlatformRole(User $user): bool
    {
        return $user->is_active && $this->activeAssignments($user->id, null)->exists();
    }

    /**
     * True when the user holds, in any tenant or on the platform, a role that requires MFA.
     */
    public function holdsRoleRequiringMfa(User $user): bool
    {
        return DB::table('user_role_assignments')
            ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $user->id)
            ->whereNull('user_role_assignments.revoked_at')
            ->where('roles.requires_mfa', true)
            ->exists();
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return is_int($version) ? $version : 1;
    }

    private function activeAssignments(int $userId, ?int $tenantId): Builder
    {
        $query = DB::table('user_role_assignments')
            ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $userId)
            ->whereNull('user_role_assignments.revoked_at');

        if ($tenantId === null) {
            return $query
                ->whereNull('user_role_assignments.tenant_id')
                ->where('roles.scope_type', RoleScope::Platform->value);
        }

        return $query
            ->where('user_role_assignments.tenant_id', $tenantId)
            ->where('roles.scope_type', RoleScope::Tenant->value)
            ->whereExists(fn (Builder $membership) => $membership
                ->selectRaw('1')
                ->from('user_tenant_memberships')
                ->join('tenants', 'tenants.id', '=', 'user_tenant_memberships.tenant_id')
                ->whereColumn('user_tenant_memberships.user_id', 'user_role_assignments.user_id')
                ->whereColumn('user_tenant_memberships.tenant_id', 'user_role_assignments.tenant_id')
                ->where('user_tenant_memberships.status', MembershipStatus::Active->value)
                ->where('tenants.status', TenantStatus::Active->value));
    }
}
