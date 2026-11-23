<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum ParticipantType: string
{
    case Prospect = 'prospect';
    case Convert = 'convert';
    case Dai = 'dai';
    case Staff = 'staff';
    case Guest = 'guest';

    /** Types that point at a record instead of a typed-in name. */
    public function isLinked(): bool
    {
        return in_array($this, [self::Prospect, self::Convert, self::Dai], true);
    }

    public function label(): string
    {
        return __('dawah.participant_types.'.$this->value);
    }
}
