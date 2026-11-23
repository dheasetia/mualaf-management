<?php

declare(strict_types=1);

namespace App\Domain\Registration\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class RegistrationRuleViolation extends RuleViolation
{
    public static function primaryDaiRequired(): self
    {
        return new self(__('registration.errors.primary_dai_required'), 'primary_dai_id');
    }

    public static function shahadahDateInFuture(): self
    {
        return new self(__('registration.errors.date_in_future'), 'shahadah_date');
    }

    public static function alreadyRegistered(): self
    {
        return new self(__('converts.errors.already_registered'), 'identity_number');
    }

    public static function sameUserCannotVerify(): self
    {
        return new self(__('registration.errors.same_user'), 'registration');
    }

    public static function possibleDuplicate(): self
    {
        return new self(__('registration.errors.possible_duplicate'), 'full_name');
    }

    public static function reviewNotesRequired(): self
    {
        return new self(__('duplicates.errors.notes_required'), 'notes');
    }

    public static function caseResolved(): self
    {
        return new self(__('duplicates.errors.resolved'), 'decision');
    }

    public static function notPending(): self
    {
        return new self(__('registration.errors.not_pending'), 'registration');
    }
}
