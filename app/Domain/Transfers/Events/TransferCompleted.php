<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * After commit; notifications and the transfer follow-up schedule (step 24) listen here.
 */
final readonly class TransferCompleted
{
    use Dispatchable;

    public function __construct(public int $transferRequestId, public int $convertId, public int $fromTenantId, public int $toTenantId) {}
}
