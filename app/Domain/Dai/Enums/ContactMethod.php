<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum ContactMethod: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Video = 'video';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return __('dai.contact_methods.'.$this->value);
    }
}
