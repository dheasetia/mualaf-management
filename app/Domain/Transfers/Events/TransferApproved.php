<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The current office approved a convert transfer; the new office accepts it.
 */
final readonly class TransferApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $transferRequestId, public int $fromTenantId, public int $toTenantId) {}
}
