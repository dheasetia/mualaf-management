<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum DisclosureScope: string
{
    case None = 'none';
    case GeneralSupport = 'general_support';
    case KnowsConversion = 'knows_conversion';

    public function label(): string
    {
        return __('converts.disclosure_scopes.'.$this->value);
    }
}
