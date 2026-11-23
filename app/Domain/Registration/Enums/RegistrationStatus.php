<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * 07 §7.1. A verifier returns a registration for correction instead of
 * rejecting it (decided 24-11-2026); rejected stays for the data model only.
 * Voided is final and needs Central approval.
 */
enum RegistrationStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Returned = 'returned';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return __('registration.registration_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::PendingVerification, self::Draft => 'warning',
            self::Returned => 'info',
            self::Rejected, self::Voided => 'gray',
        };
    }
}
