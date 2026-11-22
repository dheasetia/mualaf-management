<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Listeners;

use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Transfers\Events\TransferCompleted;

/**
 * 12 §12.3 step 8: active enrollments at the old office become transferred.
 */
final readonly class CloseEnrollmentsOnTransfer
{
    public function __construct(private ManageEnrollments $enrollments) {}

    public function handle(TransferCompleted $event): void
    {
        $this->enrollments->closeForTransfer($event->convertId, $event->fromTenantId);
    }
}
