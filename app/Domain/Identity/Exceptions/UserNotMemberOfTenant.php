<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class UserNotMemberOfTenant extends RuntimeException
{
    public static function make(int $userId, string $tenantCode): self
    {
        return new self(sprintf('User %d has no active membership in active tenant %s.', $userId, $tenantCode));
    }
}
