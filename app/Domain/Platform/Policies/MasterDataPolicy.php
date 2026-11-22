<?php

declare(strict_types=1);

namespace App\Domain\Platform\Policies;

use App\Domain\Identity\Rbac\Authorizer;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Region;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Global master data (03-tenancy-authorization §3.2): every platform user may
 * read it in Central, only `master.manage` writes. Entries are never removed
 * for good: those with deleted_at are soft-deleted (and only while nothing
 * refers to them), the others are deactivated through their `active` flag.
 */
final readonly class MasterDataPolicy
{
    public function __construct(private Authorizer $authorizer, private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return ! $this->tenantContext->has() && $this->authorizer->hasAnyPlatformRole($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->mayManage($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->mayManage($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->mayManage($user)
            && in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && ! $this->isReferenced($model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->mayManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function mayManage(User $user): bool
    {
        return ! $this->tenantContext->has() && $user->hasPermission('master.manage');
    }

    /** Foreign keys stop hard deletes only; soft deletes are checked here. */
    private function isReferenced(Model $model): bool
    {
        return match (true) {
            $model instanceof Region => $model->cities()->exists()
                || Tenant::query()->where('region_id', $model->getKey())->exists(),
            $model instanceof City => Tenant::query()->where('city_id', $model->getKey())->exists(),
            default => false,
        };
    }
}
