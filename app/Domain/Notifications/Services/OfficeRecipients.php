<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who in an office should hear about something: active members holding a
 * permission through an active role in that office, or the user behind a
 * da'i record.
 */
final readonly class OfficeRecipients
{
    public function __construct(private TenantContext $tenantContext) {}

    /** @return Collection<int, User> */
    public function withPermission(int $tenantId, string $permission, ?int $except = null): Collection
    {
        return User::query()
            ->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $tenantId)->where('status', MembershipStatus::Active))
            ->whereHas('roleAssignments', fn ($query) => $query->active()->where('tenant_id', $tenantId)
                ->whereHas('role.permissions', fn ($permissions) => $permissions->where('code', $permission)))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->get();
    }

    public function daiUser(int $tenantId, int $daiId): ?User
    {
        $userId = $this->tenantContext->run($tenantId, fn (): mixed => DB::table('dais')->where('tenant_id', $tenantId)->where('id', $daiId)->value('user_id'));

        return $userId === null ? null : User::query()->find((int) $userId);
    }
}
