<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum ContactType: string
{
    case Mobile = 'mobile';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Telegram = 'telegram';
    case Other = 'other';

    public function isPhone(): bool
    {
        return in_array($this, [self::Mobile, self::Whatsapp], true);
    }

    public function label(): string
    {
        return __('converts.contact_types.'.$this->value);
    }
}
