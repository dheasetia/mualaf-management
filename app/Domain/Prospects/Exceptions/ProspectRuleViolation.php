<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Exceptions;

use App\Domain\Prospects\Enums\ProspectStatus;
use App\Support\Exceptions\RuleViolation;

final class ProspectRuleViolation extends RuleViolation
{
    public static function possibleDuplicate(string $existingName): self
    {
        return new self(__('prospects.errors.possible_duplicate', ['name' => $existingName]), 'phone');
    }

    public static function primaryDaiRequired(): self
    {
        return new self(__('prospects.errors.primary_dai_required'), 'primary_dai_id');
    }

    public static function invalidTransition(ProspectStatus $from, ProspectStatus $to): self
    {
        return new self(__('prospects.errors.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('prospects.errors.reason_required'), 'reason');
    }

    public static function closed(): self
    {
        return new self(__('prospects.errors.closed'), 'status');
    }

    public static function notReadyForShahadah(): self
    {
        return new self(__('prospects.errors.not_ready_for_shahadah'), 'prospect_id');
    }

    public static function notAssigned(): self
    {
        return new self(__('prospects.errors.not_assigned'), 'dai_id');
    }
}
