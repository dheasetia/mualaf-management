<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Enums;

enum ProgramType: string
{
    case Umrah = 'umrah';
    case Hajj = 'hajj';

    public function label(): string
    {
        return __('pilgrimage.program_types.'.$this->value);
    }
}
