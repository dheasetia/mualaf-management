<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum FollowUpType: string
{
    case Visit = 'visit';
    case Call = 'call';
    case Message = 'message';
    case Discussion = 'discussion';
    case Dialogue = 'dialogue';
    case ClassSession = 'class';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return __('dai.follow_up_types.'.$this->value);
    }
}
