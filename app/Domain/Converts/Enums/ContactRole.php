<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

enum ContactRole: string
{
    case DormitoryHead = 'dormitory_head';
    case Hr = 'hr';
    case PublicRelations = 'public_relations';
    case Supervisor = 'supervisor';
    case Friend = 'friend';
    case Family = 'family';
    case Other = 'other';

    public function label(): string
    {
        return __('converts.contact_roles.'.$this->value);
    }
}
