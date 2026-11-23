<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Policies;

use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Materials are read with materials.view (or distribute); an office edits
 * only its own drafts, Central only national ones. ManageMaterials checks
 * every step.
 */
final readonly class DawahMaterialPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('materials.view') || $user->hasPermission('materials.distribute');
    }

    public function view(User $user, DawahMaterial $material): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('materials.create');
    }

    public function update(User $user, DawahMaterial $material): bool
    {
        return $this->create($user) && $material->tenant_id === $this->tenantContext->id() && $material->status === MaterialStatus::Draft;
    }

    public function delete(User $user, DawahMaterial $material): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
