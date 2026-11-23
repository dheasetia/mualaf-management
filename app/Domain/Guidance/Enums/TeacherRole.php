<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Enums;

enum TeacherRole: string
{
    case LeadTeacher = 'lead_teacher';
    case Assistant = 'assistant';
    case Substitute = 'substitute';

    public function label(): string
    {
        return __('guidance.teacher_roles.'.$this->value);
    }
}
