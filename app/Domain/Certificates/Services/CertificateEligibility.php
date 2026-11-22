<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Services;

use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Exceptions\CertificateRuleViolation;
use App\Domain\Examinations\Services\ExamOutcome;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Domain\Registration\Services\ShahadahDirectory;

/**
 * 09 §9.6: shahadah certificate needs a verified first shahadah; completion
 * certificate needs a completed enrollment with enough attendance and a
 * passed, finalized final exam. Checked on request and again on issue.
 */
final readonly class CertificateEligibility
{
    public function __construct(
        private ShahadahDirectory $shahadah,
        private EnrollmentDirectory $enrollments,
        private ExamOutcome $exams,
    ) {}

    public function check(CertificateType $type, int $convertId, ?int $enrollmentId): void
    {
        if ($type === CertificateType::Shahadah) {
            if ($this->shahadah->verifiedFirst($convertId) === null) {
                throw CertificateRuleViolation::shahadahNotVerified();
            }

            return;
        }

        $completion = $enrollmentId === null ? null : $this->enrollments->completion($enrollmentId);

        if ($completion === null || $completion['convert_id'] !== $convertId) {
            throw CertificateRuleViolation::enrollmentNotCompleted();
        }

        if ($completion['attendance'] !== null && (float) $completion['attendance'] < (float) $completion['minimum_attendance']) {
            throw CertificateRuleViolation::attendanceTooLow();
        }

        if ($this->exams->passedFinal((int) $enrollmentId) === null) {
            throw CertificateRuleViolation::finalExamNotPassed();
        }
    }
}
