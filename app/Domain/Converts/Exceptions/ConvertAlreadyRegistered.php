<?php

declare(strict_types=1);

namespace App\Domain\Converts\Exceptions;

use RuntimeException;

/**
 * The identity is already active on a convert record (BR-001). Thrown when the
 * database unique key on the fingerprint rejects the insert, so it also holds
 * under concurrent registrations.
 */
final class ConvertAlreadyRegistered extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('converts.errors.already_registered'));
    }
}
