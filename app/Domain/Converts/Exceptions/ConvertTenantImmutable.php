<?php

declare(strict_types=1);

namespace App\Domain\Converts\Exceptions;

use LogicException;

/**
 * origin_tenant_id never changes through ordinary saves (critical test #4);
 * current_tenant_id changes only through the transfer service (step 13).
 */
final class ConvertTenantImmutable extends LogicException
{
    public static function make(string $column): self
    {
        return new self(sprintf('converts.%s cannot be changed here.', $column));
    }
}
