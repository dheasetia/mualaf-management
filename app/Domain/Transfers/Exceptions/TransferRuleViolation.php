<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class TransferRuleViolation extends RuleViolation
{
    public static function notFound(): self
    {
        return new self(__('transfers.errors.not_found'), 'identity_number');
    }

    public static function sameOffice(): self
    {
        return new self(__('transfers.errors.same_office'), 'identity_number');
    }

    public static function alreadyActive(): self
    {
        return new self(__('transfers.errors.already_active'), 'identity_number');
    }

    public static function recentlyRejected(int $days): self
    {
        return new self(__('transfers.errors.recently_rejected', ['days' => $days]), 'identity_number');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('transfers.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function noteRequired(): self
    {
        return new self(__('transfers.errors.note_required'), 'note');
    }

    public static function notYourSide(): self
    {
        return new self(__('transfers.errors.not_your_side'), 'status');
    }

    public static function officeChanged(): self
    {
        return new self(__('transfers.errors.office_changed'), 'status');
    }

    public static function invalidDestination(): self
    {
        return new self(__('transfers.errors.invalid_destination'), 'to_tenant_id');
    }

    public static function closedProspect(): self
    {
        return new self(__('transfers.errors.closed_prospect'), 'status');
    }
}
