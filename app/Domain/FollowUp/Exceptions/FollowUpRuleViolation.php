<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class FollowUpRuleViolation extends RuleViolation
{
    public static function notADai(): self
    {
        return new self(__('followup.errors.not_a_dai'), 'dai_id');
    }

    public static function convertClosed(): self
    {
        return new self(__('followup.errors.convert_closed'), 'convert_id');
    }

    public static function invalidTransition(): self
    {
        return new self(__('followup.errors.invalid_transition'), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('followup.errors.reason_required'), 'reason');
    }

    public static function dateInPast(): self
    {
        return new self(__('followup.errors.date_in_past'), 'scheduled_at');
    }

    public static function scheduleOfOtherConvert(): self
    {
        return new self(__('followup.errors.schedule_mismatch'), 'schedule_id');
    }
}
