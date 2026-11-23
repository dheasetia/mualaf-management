<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
