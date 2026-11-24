<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Validated = 'validated';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('imports.batch_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Failed, self::Cancelled => 'danger',
            self::Validated => 'info',
            self::Uploaded, self::Validating, self::Importing => 'warning',
        };
    }
}
