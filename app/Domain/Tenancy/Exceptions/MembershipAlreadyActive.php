<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class MembershipAlreadyActive extends RuntimeException
{
    public static function make(int $userId, int $tenantId): self
    {
        return new self(sprintf('User %d already has an active membership in tenant %d.', $userId, $tenantId));
    }
}
