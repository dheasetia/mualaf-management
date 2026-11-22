<?php

declare(strict_types=1);

namespace App\Domain\Converts\Exceptions;

use App\Domain\Converts\Enums\ConvertStatus;
use App\Support\Exceptions\RuleViolation;

final class ConvertRuleViolation extends RuleViolation
{
    public static function reasonRequired(): self
    {
        return new self(__('converts.errors.reason_required'), 'reason');
    }

    public static function invalidTransition(ConvertStatus $from, ConvertStatus $to): self
    {
        return new self(__('converts.errors.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]), 'status');
    }

    public static function closed(): self
    {
        return new self(__('converts.errors.closed'), 'status');
    }

    public static function invalidMapsUrl(): self
    {
        return new self(__('converts.errors.invalid_maps_url'), 'google_maps_url');
    }

    public static function invalidContact(): self
    {
        return new self(__('converts.errors.invalid_contact'), 'value');
    }
}
