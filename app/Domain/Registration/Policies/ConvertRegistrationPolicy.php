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
            && ($user->hasPermission('shahadah.record') || $user->hasPermission('shahadah.verify') || $user->hasPermission('shahadah.request_void'));
    }

    public function view(User $user, ConvertRegistration $registration): bool
    {
        return $this->viewAny($user) && $registration->tenant_id === $this->tenantContext->id();
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('shahadah.record');
    }

    /** Hidden for the registering or correcting user too; VerifyShahadah enforces the same rule. */
    public function verify(User $user, ConvertRegistration $registration): bool
    {
        return $this->view($user, $registration)
            && $user->hasPermission('shahadah.verify')
            && $registration->status === RegistrationStatus::PendingVerification
            && ! in_array($user->id, [$registration->registered_by, $registration->corrected_by], true);
    }

    /** Same people as verify: the verifier returns instead of rejecting. */
    public function returnForCorrection(User $user, ConvertRegistration $registration): bool
    {
        return $this->verify($user, $registration);
    }

    public function correct(User $user, ConvertRegistration $registration): bool
    {
        return $this->view($user, $registration)
            && $user->hasPermission('shahadah.record')
            && $registration->status === RegistrationStatus::Returned;
    }

    public function requestVoid(User $user, ConvertRegistration $registration): bool
    {
        return $this->tenantContext->has()
            && $registration->tenant_id === $this->tenantContext->id()
            && $user->hasPermission('shahadah.request_void')
            && $registration->status !== RegistrationStatus::Voided;
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
