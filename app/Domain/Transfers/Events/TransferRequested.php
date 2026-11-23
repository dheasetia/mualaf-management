<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A convert transfer was requested; the current office reviews it.
 */
final readonly class TransferRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $transferRequestId, public int $fromTenantId, public int $toTenantId) {}
}
