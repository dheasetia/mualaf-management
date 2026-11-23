<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ConsentMethod: string
{
    case Written = 'written';
    case VerbalWitnessed = 'verbal_witnessed';
    case Digital = 'digital';

    public function label(): string
    {
        return __('international.consent_methods.'.$this->value);
    }
}
