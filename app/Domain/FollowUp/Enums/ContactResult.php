<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum ContactResult: string
{
    case Reached = 'reached';
    case NotReached = 'not_reached';
    case WrongNumber = 'wrong_number';
    case Refused = 'refused';

    public function label(): string
    {
        return __('followup.contact_results.'.$this->value);
    }
}
