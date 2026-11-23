<?php

declare(strict_types=1);

namespace App\Support\Database;

use LogicException;

final class AppendOnlyViolation extends LogicException
{
    public static function make(string $model, string $operation): self
    {
        return new self(sprintf('%s is append-only; %s is not allowed.', $model, $operation));
    }
}
