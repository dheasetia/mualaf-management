<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

final class TenantMismatch extends RuntimeException
{
    public static function onCreate(string $model, int $given, int $context): self
    {
        return new self(sprintf('Cannot create %s for tenant %d while tenant context is %d.', $model, $given, $context));
    }

    public static function onUpdate(string $model): self
    {
        return new self(sprintf('The tenant of %s cannot be changed through an update.', $model));
    }
}
