<?php

declare(strict_types=1);

namespace App\Domain\Converts\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class IdentityRuleViolation extends RuleViolation
{
    public static function invalidNumber(): self
    {
        return new self(__('converts.errors.invalid_identity_number'), 'identity_number');
    }

    public static function issuingCountryRequired(): self
    {
        return new self(__('converts.errors.issuing_country_required'), 'issuing_country_id');
    }

    public static function searchLimitReached(int $minutes): self
    {
        return new self(__('converts.errors.search_limit', ['minutes' => $minutes]), 'identity_number');
    }
}
