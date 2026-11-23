<?php

declare(strict_types=1);

namespace App\Domain\International\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class InternationalRuleViolation extends RuleViolation
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('international.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('international.errors.reason_required'), 'reason');
    }

    public static function consentMissing(): self
    {
        return new self(__('international.errors.consent_missing'), 'consent_record_id');
    }

    public static function scopeRequired(): self
    {
        return new self(__('international.errors.scope_required'), 'information_scope');
    }

    public static function alreadyWithdrawn(): self
    {
        return new self(__('international.errors.already_withdrawn'), 'status');
    }

    public static function witnessRequired(): self
    {
        return new self(__('international.errors.witness_required'), 'witnessed_by');
    }

    public static function checklistOpen(): self
    {
        return new self(__('international.errors.checklist_open'), 'convert_id');
    }

    public static function itemsPending(): self
    {
        return new self(__('international.errors.items_pending'), 'status');
    }

    public static function organizationUnavailable(): self
    {
        return new self(__('international.errors.organization_unavailable'), 'external_organization_id');
    }
}
