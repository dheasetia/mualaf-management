<?php

declare(strict_types=1);

namespace App\Domain\Registration\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after commit; Shahadah Verifier notifications listen here (later step). */
final readonly class ShahadahRecorded
{
    use Dispatchable;

    public function __construct(public int $registrationId, public int $tenantId) {}
}
