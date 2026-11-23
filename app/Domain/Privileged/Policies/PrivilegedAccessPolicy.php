<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Policies;

use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Break-glass requests live in Central: requesters see their own, approvers
 * see all. PrivilegedAccess checks every step.
 */
final readonly class PrivilegedAccessPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return ! $this->tenantContext->has() && ($user->hasPermission('privileged.request') || $user->hasPermission('privileged.approve'));
    }

    public function view(User $user, PrivilegedAccessRequest $request): bool
    {
        return $this->viewAny($user) && ($request->requested_by === $user->id || $user->hasPermission('privileged.approve'));
    }

    public function create(User $user): bool
    {
        return ! $this->tenantContext->has() && $user->hasPermission('privileged.request');
    }

    public function update(User $user, PrivilegedAccessRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, PrivilegedAccessRequest $request): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
