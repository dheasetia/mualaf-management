<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum Confidentiality: string
{
    case Normal = 'normal';
    case Confidential = 'confidential';

    public function label(): string
    {
        return __('dai.confidentiality.'.$this->value);
    }
}
