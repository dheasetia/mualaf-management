<?php

declare(strict_types=1);

namespace App\Domain\International\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 10 §10.8: a consent was withdrawn (its referrals are already closed).
 */
final readonly class ConsentWithdrawn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $consentId, public int $convertId, public int $tenantId, public int $referralsClosed, public ?int $actorId = null) {}
}
