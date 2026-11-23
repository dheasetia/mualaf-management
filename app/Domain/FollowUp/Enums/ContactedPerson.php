<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum ContactedPerson: string
{
    case Convert = 'convert';
    case Family = 'family';
    case Employer = 'employer';
    case Friend = 'friend';
    case LocalDai = 'local_dai';
    case Other = 'other';

    public function label(): string
    {
        return __('followup.contacted_persons.'.$this->value);
    }
}
