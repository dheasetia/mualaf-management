<?php

declare(strict_types=1);

namespace App\Domain\Companies\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class CompanyRuleViolation extends RuleViolation
{
    public static function notOwned(): self
    {
        return new self(__('companies.errors.not_owned'), 'name_ar');
    }

    public static function invalidMapsUrl(): self
    {
        return new self(__('companies.errors.invalid_maps_url'), 'google_maps_url');
    }

    public static function alreadyNational(): self
    {
        return new self(__('companies.errors.already_national'), 'name_ar');
    }
}
