<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum SharedField: string
{
    case Name = 'name';
    case IslamicName = 'islamic_name';
    case Gender = 'gender';
    case ShahadahDate = 'shahadah_date';
    case GuidanceStatus = 'guidance_status';
    case Languages = 'languages';
    case Contact = 'contact';
    case HomeCity = 'home_city';
    case SupportNeeded = 'support_needed';

    public function label(): string
    {
        return __('international.shared_fields.'.$this->value);
    }
}
