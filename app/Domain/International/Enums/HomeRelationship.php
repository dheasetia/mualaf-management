<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum HomeRelationship: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case Child = 'child';
    case Relative = 'relative';
    case Friend = 'friend';
    case MuslimContact = 'muslim_contact';
    case LocalDai = 'local_dai';
    case Other = 'other';

    public function label(): string
    {
        return __('international.relationships.'.$this->value);
    }
}
