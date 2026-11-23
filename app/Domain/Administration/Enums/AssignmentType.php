<?php

declare(strict_types=1);

namespace App\Domain\Administration\Enums;

enum AssignmentType: string
{
    case Verifier = 'verifier';
    case Processor = 'processor';
    case Approver = 'approver';

    /** The permission the assignee must hold. */
    public function permission(): string
    {
        return match ($this) {
            self::Verifier => 'submissions.verify',
            self::Processor => 'submissions.process',
            self::Approver => 'submissions.approve',
        };
    }

    public function label(): string
    {
        return __('submissions.assignment_types.'.$this->value);
    }
}
