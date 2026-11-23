<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum OrganizationType: string
{
    case Mosque = 'mosque';
    case IslamicCenter = 'islamic_center';
    case DawahOrg = 'dawah_org';
    case School = 'school';
    case IndividualDai = 'individual_dai';

    public function label(): string
    {
        return __('international.organization_types.'.$this->value);
    }
}
