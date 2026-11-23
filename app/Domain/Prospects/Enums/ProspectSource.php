<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Enums;

enum ProspectSource: string
{
    case WalkIn = 'walk_in';
    case DaiContact = 'dai_contact';
    case ReferralConvert = 'referral_convert';
    case ReferralCompany = 'referral_company';
    case Event = 'event';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return __('prospects.sources.'.$this->value);
    }
}
