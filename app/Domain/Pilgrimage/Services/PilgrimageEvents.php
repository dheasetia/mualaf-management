<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Services;

use App\Domain\Pilgrimage\Models\PilgrimageEvent;
use App\Models\User;

/**
 * Writes the append-only program history.
 */
final class PilgrimageEvents
{
    /** @param  array<string, mixed>  $metadata */
    public function record(int $programId, ?int $registrationId, ?User $actor, string $type, array $metadata = []): void
    {
        $event = new PilgrimageEvent;
        $event->program_id = $programId;
        $event->registration_id = $registrationId;
        $event->actor_id = $actor?->id;
        $event->event_type = $type;
        $event->metadata = $metadata;
        $event->save();
    }
}
