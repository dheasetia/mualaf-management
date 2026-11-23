<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class InvalidRoleScope extends RuntimeException
{
    public static function tenantRoleNeedsTenant(string $role): self
    {
        return new self(sprintf('Role %s is an office role and must be assigned within a tenant.', $role));
    }

    public static function platformRoleWithTenant(string $role): self
    {
        return new self(sprintf('Role %s is a platform role and cannot be assigned within a tenant.', $role));
    }
}
