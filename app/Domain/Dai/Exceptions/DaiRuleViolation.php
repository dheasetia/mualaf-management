<?php

declare(strict_types=1);

namespace App\Domain\Dai\Exceptions;

use RuntimeException;

/**
 * A da'i business rule was broken. Carries the form field it concerns so the
 * panel can show it next to that field; the message is already translated.
 */
final class DaiRuleViolation extends RuntimeException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function duplicateEmployeeNumber(): self
    {
        return new self(__('dai.errors.duplicate_employee_number'), 'employee_number');
    }

    public static function userAlreadyLinked(): self
    {
        return new self(__('dai.errors.user_already_linked'), 'user_id');
    }

    public static function userNotMember(): self
    {
        return new self(__('dai.errors.user_not_member'), 'user_id');
    }

    public static function supervisorIsSelf(): self
    {
        return new self(__('dai.errors.supervisor_self'), 'supervisor_dai_id');
    }

    public static function supervisorCycle(): self
    {
        return new self(__('dai.errors.supervisor_cycle'), 'supervisor_dai_id');
    }

    public static function supervisorUnavailable(): self
    {
        return new self(__('dai.errors.supervisor_unavailable'), 'supervisor_dai_id');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('dai.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function hasActivePrimaryAssignments(int $count): self
    {
        return new self(__('dai.errors.has_active_primary', ['count' => $count]), 'status');
    }
}
