<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class GuidanceRuleViolation extends RuleViolation
{
    public static function notOwnProgram(): self
    {
        return new self(__('guidance.errors.not_own_program'), 'program_id');
    }

    public static function programUnavailable(): self
    {
        return new self(__('guidance.errors.program_unavailable'), 'program_id');
    }

    public static function classUnavailable(): self
    {
        return new self(__('guidance.errors.class_unavailable'), 'class_id');
    }

    public static function classFull(): self
    {
        return new self(__('guidance.errors.class_full'), 'class_id');
    }

    public static function genderPolicy(): self
    {
        return new self(__('guidance.errors.gender_policy'), 'class_id');
    }

    public static function convertUnavailable(): self
    {
        return new self(__('guidance.errors.convert_unavailable'), 'convert_id');
    }

    public static function alreadyEnrolled(): self
    {
        return new self(__('guidance.errors.already_enrolled'), 'program_id');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('guidance.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('guidance.errors.reason_required'), 'reason');
    }

    public static function attendanceTooLow(string $required): self
    {
        return new self(__('guidance.errors.attendance_too_low', ['required' => $required]), 'status');
    }

    public static function notYourClass(): self
    {
        return new self(__('guidance.errors.not_your_class'), 'class_id');
    }

    public static function notAMember(): self
    {
        return new self(__('guidance.errors.not_a_member'), 'user_id');
    }

    public static function teacherAlreadyAssigned(): self
    {
        return new self(__('guidance.errors.teacher_already_assigned'), 'user_id');
    }

    public static function sessionCancelled(): self
    {
        return new self(__('guidance.errors.session_cancelled'), 'status');
    }

    public static function enrollmentNotInClass(): self
    {
        return new self(__('guidance.errors.enrollment_not_in_class'), 'enrollment_id');
    }

    public static function lessonNotInProgram(): self
    {
        return new self(__('guidance.errors.lesson_not_in_program'), 'lesson_id');
    }

    public static function lessonInUse(): self
    {
        return new self(__('guidance.errors.lesson_in_use'), 'lessons');
    }
}
