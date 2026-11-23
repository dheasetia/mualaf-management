<?php

declare(strict_types=1);

namespace App\Domain\Privileged\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class PrivilegedRuleViolation extends RuleViolation
{
    public static function reasonRequired(): self
    {
        return new self(__('privileged.errors.reason_required'), 'reason');
    }

    public static function scopeRequired(): self
    {
        return new self(__('privileged.errors.scope_required'), 'requested_scope');
    }

    public static function durationInvalid(): self
    {
        return new self(__('privileged.errors.duration_invalid'), 'requested_duration_minutes');
    }

    public static function convertNotFound(): self
    {
        return new self(__('privileged.errors.convert_not_found'), 'identity_number');
    }

    public static function selfApproval(): self
    {
        return new self(__('privileged.errors.self_approval'), 'status');
    }

    public static function notPending(): self
    {
        return new self(__('privileged.errors.not_pending'), 'status');
    }

    public static function notActive(): self
    {
        return new self(__('privileged.errors.not_active'), 'status');
    }

    public static function platformOnly(): self
    {
        return new self(__('privileged.errors.platform_only'), 'status');
    }
}
