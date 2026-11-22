<?php

declare(strict_types=1);

namespace App\Domain\Dai\Enums;

enum AssignmentType: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case TeacherSupport = 'teacher_support';
    case Temporary = 'temporary';
}
