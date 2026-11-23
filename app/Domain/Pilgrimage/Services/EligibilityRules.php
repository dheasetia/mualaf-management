<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Registration\Services\ShahadahDirectory;
use Carbon\CarbonImmutable;

/**
 * A program's eligibility rules (10 §10.2): the convert's record is open,
 * the shahadah is verified and at least `min_months_since_shahadah` old by
 * the program's start, and, when `require_completed_guidance`, a guidance
 * program has been completed. Returns the unmet rules as messages.
 */
final readonly class EligibilityRules
{
    public function __construct(private ShahadahDirectory $shahadah, private EnrollmentDirectory $enrollments) {}

    /** @return list<string> */
    public function failures(PilgrimageProgram $program, int $convertId): array
    {
        $rules = $program->eligibility_rules;
        $failures = [];
        $convert = Convert::query()->find($convertId);

        if ($convert === null || $convert->status->isTerminal()) {
            return [(string) __('pilgrimage.eligibility.record_closed')];
        }

        $shahadah = $this->shahadah->verifiedFirst($convertId);
        $months = (int) ($rules['min_months_since_shahadah'] ?? 0);

        if ($shahadah === null) {
            $failures[] = (string) __('pilgrimage.eligibility.shahadah_not_verified');
        } elseif ($months > 0 && CarbonImmutable::parse($shahadah['date']->toDateString())->addMonths($months)->greaterThan($program->starts_on)) {
            $failures[] = (string) __('pilgrimage.eligibility.too_recent', ['months' => $months]);
        }

        if (($rules['require_completed_guidance'] ?? false) && ! $this->enrollments->completed()->contains($convertId)) {
            $failures[] = (string) __('pilgrimage.eligibility.guidance_not_completed');
        }

        return $failures;
    }
}
