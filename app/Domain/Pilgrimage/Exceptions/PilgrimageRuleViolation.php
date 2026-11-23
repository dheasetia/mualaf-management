<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class PilgrimageRuleViolation extends RuleViolation
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('pilgrimage.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('pilgrimage.errors.reason_required'), 'reason');
    }

    public static function notEditable(): self
    {
        return new self(__('pilgrimage.errors.not_editable'), 'status');
    }

    public static function datesInvalid(): self
    {
        return new self(__('pilgrimage.errors.dates_invalid'), 'ends_on');
    }

    public static function capacityBelowSeats(): self
    {
        return new self(__('pilgrimage.errors.capacity_below_seats'), 'capacity');
    }

    public static function registrationClosed(): self
    {
        return new self(__('pilgrimage.errors.registration_closed'), 'program_id');
    }

    public static function convertUnavailable(): self
    {
        return new self(__('pilgrimage.errors.convert_unavailable'), 'convert_id');
    }

    public static function alreadyRegistered(): self
    {
        return new self(__('pilgrimage.errors.already_registered'), 'convert_id');
    }

    public static function programFull(): self
    {
        return new self(__('pilgrimage.errors.program_full'), 'status');
    }

    public static function selfApproval(): self
    {
        return new self(__('pilgrimage.errors.self_approval'), 'status');
    }

    public static function groupFull(): self
    {
        return new self(__('pilgrimage.errors.group_full'), 'group_id');
    }

    public static function notInProgram(): self
    {
        return new self(__('pilgrimage.errors.not_in_program'), 'registration_id');
    }

    public static function duplicateRequirement(): self
    {
        return new self(__('pilgrimage.errors.duplicate_requirement'), 'code');
    }

    public static function leaderUnavailable(): self
    {
        return new self(__('pilgrimage.errors.leader_unavailable'), 'leader_user_id');
    }

    public static function consentMissing(): self
    {
        return new self(__('pilgrimage.eligibility.consent_missing'), 'consent');
    }
}
