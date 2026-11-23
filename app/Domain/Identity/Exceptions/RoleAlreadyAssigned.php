<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class RoleAlreadyAssigned extends RuntimeException
{
    public static function make(string $role): self
    {
        return new self(sprintf('The user already holds role %s in this scope.', $role));
    }
}
