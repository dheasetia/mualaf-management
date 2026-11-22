<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum ProgressSource: string
{
    case Direct = 'direct';
    case Transferred = 'transferred';

    public function label(): string
    {
        return __('guidance.progress_sources.'.$this->value);
    }
}
