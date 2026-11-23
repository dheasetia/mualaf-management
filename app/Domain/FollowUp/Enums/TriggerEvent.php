<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum TriggerEvent: string
{
    case Shahadah = 'shahadah';
    case GuidanceCompleted = 'guidance_completed';
    case ReturnedHome = 'returned_home';
    case TransferCompleted = 'transfer_completed';

    public function label(): string
    {
        return __('followup.triggers.'.$this->value);
    }
}
