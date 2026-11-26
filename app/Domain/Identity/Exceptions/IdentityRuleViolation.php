<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class IdentityRuleViolation extends RuleViolation
{
    public static function ownAccount(): self
    {
        return new self(__('users.errors.own_account'), 'email');
    }

    public static function emailTaken(): self
    {
        return new self(__('users.errors.email_taken'), 'email');
    }

    public static function userNotFound(): self
    {
        return new self(__('users.errors.user_not_found'), 'email');
    }

    public static function alreadyInOffice(): self
    {
        return new self(__('users.errors.already_in_office'), 'email');
    }

    public static function notInOffice(): self
    {
        return new self(__('users.errors.not_in_office'), 'email');
    }

    public static function revokeReasonRequired(): self
    {
        return new self(__('users.errors.revoke_reason_required'), 'reason');
    }

    public static function roleScopeMismatch(): self
    {
        return new self(__('users.errors.role_scope_mismatch'), 'roles');
    }

    public static function systemRoleLocked(): self
    {
        return new self(__('roles.errors.system_role_locked'), 'name_en');
    }

    public static function roleCodeTaken(): self
    {
        return new self(__('roles.errors.code_taken'), 'name_en');
    }

    public static function permissionNotAllowed(string $permission): self
    {
        return new self(__('roles.errors.permission_not_allowed', ['permission' => $permission]), 'permissions');
    }

    public static function permissionsRequired(): self
    {
        return new self(__('roles.errors.permissions_required'), 'permissions');
    }

    public static function roleInUse(): self
    {
        return new self(__('roles.errors.in_use'), 'name_en');
    }
}
