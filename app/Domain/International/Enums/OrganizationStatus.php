<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum OrganizationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('international.organization_statuses.'.$this->value);
    }
}
