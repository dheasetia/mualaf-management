<?php

declare(strict_types=1);

namespace App\Domain\Converts\Enums;

/**
 * Administrative record status only, never a statement about faith (06 §6.8).
 */
enum ConvertStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
    case RecordClosed = 'record_closed';
}
