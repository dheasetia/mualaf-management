<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ReviewDecision: string
{
    case SamePerson = 'same_person';
    case DifferentPerson = 'different_person';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return __('duplicates.decisions.'.$this->value);
    }
}
