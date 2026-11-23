<?php

declare(strict_types=1);

namespace App\Domain\Registration\Policies;

use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

final readonly class ConvertRegistrationPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            && ($user->hasPermission('shahadah.record') || $user->hasPermission('shahadah.verify'));
    }

    public function view(User $user, ConvertRegistration $registration): bool
    {
        return $this->viewAny($user) && $registration->tenant_id === $this->tenantContext->id();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('shahadah.record');
    }

    /** Hidden for the registering user too; VerifyShahadah enforces the same rule. */
    public function verify(User $user, ConvertRegistration $registration): bool
    {
        return $this->view($user, $registration)
            && $user->hasPermission('shahadah.verify')
            && $registration->status === RegistrationStatus::PendingVerification
            && $registration->registered_by !== $user->id;
    }

    public function update(User $user, ConvertRegistration $registration): bool
    {
        return false;
    }

    public function delete(User $user, ConvertRegistration $registration): bool
    {
        return false;
    }
}
