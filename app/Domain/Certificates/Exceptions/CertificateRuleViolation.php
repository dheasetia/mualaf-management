<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class CertificateRuleViolation extends RuleViolation
{
    public static function shahadahNotVerified(): self
    {
        return new self(__('certificates.errors.shahadah_not_verified'), 'convert_id');
    }

    public static function enrollmentNotCompleted(): self
    {
        return new self(__('certificates.errors.enrollment_not_completed'), 'enrollment_id');
    }

    public static function attendanceTooLow(): self
    {
        return new self(__('certificates.errors.attendance_too_low'), 'enrollment_id');
    }

    public static function finalExamNotPassed(): self
    {
        return new self(__('certificates.errors.final_exam_not_passed'), 'enrollment_id');
    }

    public static function alreadyExists(): self
    {
        return new self(__('certificates.errors.already_exists'), 'convert_id');
    }

    public static function convertUnavailable(): self
    {
        return new self(__('certificates.errors.convert_unavailable'), 'convert_id');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('certificates.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function selfApproval(): self
    {
        return new self(__('certificates.errors.self_approval'), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('certificates.errors.reason_required'), 'reason');
    }

    public static function noTemplate(): self
    {
        return new self(__('certificates.errors.no_template'), 'certificate_template_id');
    }

    public static function templateNotEditable(): self
    {
        return new self(__('certificates.errors.template_not_editable'), 'html_template');
    }
}
