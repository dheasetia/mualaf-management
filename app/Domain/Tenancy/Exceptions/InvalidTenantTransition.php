<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use App\Domain\Tenancy\Enums\TenantStatus;
use RuntimeException;

final class InvalidTenantTransition extends RuntimeException
{
    public static function make(TenantStatus $from, TenantStatus $to): self
    {
        return new self(sprintf('Tenant cannot move from %s to %s.', $from->value, $to->value));
    }
}
