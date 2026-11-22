<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

/**
 * 04-identity-privacy §4.6.
 */
enum DisclosureLevel: string
{
    case Normal = 'normal';
    case Restricted = 'restricted';
    case HighConfidentiality = 'high_confidentiality';

    public function label(): string
    {
        return __('converts.disclosure_levels.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Restricted => 'warning',
            self::HighConfidentiality => 'danger',
        };
    }
}
