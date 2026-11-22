<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return __('registration.registration_statuses.'.$this->value);
    }
}
