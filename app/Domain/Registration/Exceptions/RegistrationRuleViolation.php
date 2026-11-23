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

    public static function witnessesRequired(int $minimum): self
    {
        return new self(trans_choice('registration.errors.witnesses_required', $minimum, ['count' => $minimum]), 'witnesses');
    }

    public static function tooManyWitnesses(int $maximum): self
    {
        return new self(__('registration.errors.too_many_witnesses', ['count' => $maximum]), 'witnesses');
    }

    public static function invalidWitness(): self
    {
        return new self(__('registration.errors.invalid_witness'), 'witnesses');
    }

    public static function duplicateWitness(): self
    {
        return new self(__('registration.errors.duplicate_witness'), 'witnesses');
    }

    public static function noteRequired(): self
    {
        return new self(__('registration.errors.note_required'), 'note');
    }

    public static function notReturned(): self
    {
        return new self(__('registration.errors.not_returned'), 'registration');
    }

    public static function alreadyVoided(): self
    {
        return new self(__('registration.errors.already_voided'), 'registration');
    }

    public static function voidRequestOpen(): self
    {
        return new self(__('registration.errors.void_request_open'), 'registration');
    }

    public static function voidRequestDecided(): self
    {
        return new self(__('registration.errors.void_request_decided'), 'request');
    }

    public static function selfDecision(): self
    {
        return new self(__('registration.errors.self_decision'), 'request');
    }
}
