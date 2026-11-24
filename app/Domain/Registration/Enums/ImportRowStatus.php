<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Imported = 'imported';
    case Duplicate = 'duplicate';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('imports.row_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Imported => 'success',
            self::Invalid => 'danger',
            self::Duplicate => 'warning',
            self::Valid => 'info',
            self::Pending, self::Skipped => 'gray',
        };
    }
}
