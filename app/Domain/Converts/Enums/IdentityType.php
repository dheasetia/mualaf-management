<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

/**
 * 04-identity-privacy §4.1.
 */
enum IdentityType: string
{
    case NationalId = 'national_id';
    case Iqamah = 'iqamah';
    case Passport = 'passport';
    case GccId = 'gcc_id';
    case BorderNumber = 'border_number';
    case Other = 'other';

    /** Types the Kingdom issues: the issuing country is always SA. */
    public function issuedBySaudiArabia(): bool
    {
        return in_array($this, [self::NationalId, self::Iqamah, self::BorderNumber], true);
    }

    public function label(): string
    {
        return __('converts.identity_types.'.$this->value);
    }
}
