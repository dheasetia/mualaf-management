<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class CannotChangeOwnRoles extends RuntimeException
{
    public static function make(): self
    {
        return new self('Users cannot assign or revoke their own roles.');
    }
}
