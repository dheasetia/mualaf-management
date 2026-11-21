<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class MissingTenantContext extends RuntimeException
{
    public static function make(): self
    {
        return new self('Tenant context is required but has not been set.');
    }
}
