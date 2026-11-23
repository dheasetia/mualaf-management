<?php

declare(strict_types=1);

namespace App\Domain\Registration\Policies;

use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Registration\Models\ShahadahVoidRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Void requests are decided in Central by shahadah.approve_void holders;
 * offices ask from the registration list. ShahadahVoiding checks every step.
 */
final readonly class ShahadahVoidRequestPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return ! $this->tenantContext->has() && $user->hasPermission('shahadah.approve_void');
    }

    public function view(User $user, ShahadahVoidRequest $request): bool
    {
        return $this->viewAny($user);
    }

    public function decide(User $user, ShahadahVoidRequest $request): bool
    {
        return $this->viewAny($user) && $request->status === VoidRequestStatus::Pending && $request->requested_by !== $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ShahadahVoidRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, ShahadahVoidRequest $request): bool
    {
        return false;
    }
}
