<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class ExamRuleViolation extends RuleViolation
{
    public static function programUnavailable(): self
    {
        return new self(__('exams.errors.program_unavailable'), 'program_id');
    }

    public static function classNotInProgram(): self
    {
        return new self(__('exams.errors.class_not_in_program'), 'class_id');
    }

    public static function componentsRequired(): self
    {
        return new self(__('exams.errors.components_required'), 'components');
    }

    public static function passingAboveMaximum(): self
    {
        return new self(__('exams.errors.passing_above_maximum'), 'passing_score');
    }

    public static function notEditable(): self
    {
        return new self(__('exams.errors.not_editable'), 'status');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('exams.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function enrollmentNotEligible(): self
    {
        return new self(__('exams.errors.enrollment_not_eligible'), 'enrollment_ids');
    }

    public static function attemptOpen(): self
    {
        return new self(__('exams.errors.attempt_open'), 'enrollment_ids');
    }

    public static function alreadyPassed(): self
    {
        return new self(__('exams.errors.already_passed'), 'enrollment_ids');
    }

    public static function notYourClass(): self
    {
        return new self(__('exams.errors.not_your_class'), 'scores');
    }

    public static function scoresIncomplete(): self
    {
        return new self(__('exams.errors.scores_incomplete'), 'scores');
    }

    public static function scoreOutOfRange(string $component): self
    {
        return new self(__('exams.errors.score_out_of_range', ['component' => $component]), 'scores');
    }

    public static function notGraded(): self
    {
        return new self(__('exams.errors.not_graded'), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('exams.errors.reason_required'), 'reason');
    }

    public static function notFinalized(): self
    {
        return new self(__('exams.errors.not_finalized'), 'status');
    }
}
