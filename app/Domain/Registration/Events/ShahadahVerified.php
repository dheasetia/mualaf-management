<?php

declare(strict_types=1);

namespace App\Domain\Registration\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after commit. Follow-up schedules (step 24), default enrollment
 * (step 15) and the shahadah certificate request (step 17) listen here.
 */
final readonly class ShahadahVerified
{
    use Dispatchable;

    public function __construct(public int $registrationId, public int $convertId, public int $tenantId) {}
}
