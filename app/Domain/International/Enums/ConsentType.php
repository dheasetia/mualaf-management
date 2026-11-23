<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ConsentType: string
{
    case InternationalSharing = 'international_sharing';
    case ContactEmployer = 'contact_employer';
    case ContactFamily = 'contact_family';
    case PhotoUse = 'photo_use';
    case PilgrimageParticipation = 'pilgrimage_participation';

    public function label(): string
    {
        return __('international.consent_types.'.$this->value);
    }
}
