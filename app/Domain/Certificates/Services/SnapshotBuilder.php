<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Services;

use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Models\Convert;
use App\Domain\Examinations\Services\ExamOutcome;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Domain\Registration\Services\ShahadahDirectory;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Dates\HijriDate;
use Carbon\CarbonImmutable;

/**
 * What a certificate prints, frozen at issue (12 §12.4 step 5): later edits
 * of the convert, program or office never change an issued certificate.
 * Gregorian and Umm al-Qura dates are both included; the template chooses
 * (open question #1).
 */
final readonly class SnapshotBuilder
{
    public function __construct(
        private ShahadahDirectory $shahadah,
        private EnrollmentDirectory $enrollments,
        private ExamOutcome $exams,
    ) {}

    /** @return array<string, string|null> */
    public function build(Certificate $certificate, Convert $convert, Tenant $tenant, CarbonImmutable $issuedAt): array
    {
        $snapshot = [
            'certificate_number' => $certificate->certificate_number,
            'certificate_type_ar' => __('certificates.types.'.$certificate->certificate_type->value, [], 'ar'),
            'certificate_type_en' => __('certificates.types.'.$certificate->certificate_type->value, [], 'en'),
            'full_name' => $convert->full_name,
            'name_ar' => $convert->name_ar,
            'islamic_name' => $convert->islamic_name,
            'office_name_ar' => $tenant->name_ar,
            'office_name_en' => $tenant->name_en,
            'director_name' => $tenant->getAttribute('director_name'),
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_gregorian_ar' => HijriDate::gregorian($issuedAt),
            'issued_on_hijri_ar' => HijriDate::format($issuedAt),
        ];

        if ($certificate->certificate_type === CertificateType::Shahadah) {
            $shahadah = $this->shahadah->verifiedFirst($convert->id);

            return [
                ...$snapshot,
                'shahadah_date' => $shahadah === null ? null : $shahadah['date']->toDateString(),
                'shahadah_date_gregorian_ar' => $shahadah === null ? null : HijriDate::gregorian($shahadah['date']),
                'shahadah_date_hijri_ar' => $shahadah === null ? null : HijriDate::format($shahadah['date']),
                'shahadah_location' => $shahadah['location'] ?? null,
            ];
        }

        $completion = $this->enrollments->completion((int) $certificate->enrollment_id);

        return [
            ...$snapshot,
            'program_name_ar' => $completion['program_name_ar'] ?? null,
            'program_name_en' => $completion['program_name_en'] ?? null,
            'completed_on' => $completion === null ? null : $completion['completed_at']->toDateString(),
            'completed_on_hijri_ar' => $completion === null ? null : HijriDate::format($completion['completed_at']),
            'attendance_percentage' => $completion['attendance'] ?? null,
            'final_score' => $this->exams->passedFinal((int) $certificate->enrollment_id),
        ];
    }
}
