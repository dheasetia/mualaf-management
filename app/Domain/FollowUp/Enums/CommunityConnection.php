<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum CommunityConnection: string
{
    case Connected = 'connected';
    case Partially = 'partially';
    case NotConnected = 'not_connected';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('followup.community_connections.'.$this->value);
    }
}
