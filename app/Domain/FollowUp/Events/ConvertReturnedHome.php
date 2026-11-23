<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after commit when a convert's follow-up status becomes
 * returned_home: follow-up schedules and the pre-departure checklist.
 */
final readonly class ConvertReturnedHome implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $convertId, public int $tenantId, public ?int $countryId, public int $actorId) {}
}
