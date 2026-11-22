<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum ActorType: string
{
    case User = 'user';
    case System = 'system';
    case Job = 'job';
}
