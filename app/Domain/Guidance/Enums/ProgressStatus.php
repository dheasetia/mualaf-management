<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum ProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NeedsSupport = 'needs_support';

    public function label(): string
    {
        return __('guidance.progress_statuses.'.$this->value);
    }
}
