<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Enums;

enum CertificateStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Issued = 'issued';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
    case Replaced = 'replaced';

    public function label(): string
    {
        return __('certificates.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::PendingApproval => 'warning',
            self::Approved => 'info',
            self::Issued => 'success',
            self::Rejected, self::Revoked, self::Replaced => 'danger',
        };
    }
}
