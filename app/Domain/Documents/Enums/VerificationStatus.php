<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('documents.verification_statuses.'.$this->value);
    }
}
