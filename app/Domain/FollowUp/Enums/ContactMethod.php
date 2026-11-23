<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Enums;

enum ContactMethod: string
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Visit = 'visit';
    case VideoCall = 'video_call';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return __('followup.contact_methods.'.$this->value);
    }
}
