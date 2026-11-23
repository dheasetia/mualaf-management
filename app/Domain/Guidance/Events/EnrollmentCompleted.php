<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after commit when an enrollment completes; follow-up
 * schedules (10 §10.4) listen here.
 */
final readonly class EnrollmentCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $enrollmentId, public int $convertId, public int $tenantId) {}
}
