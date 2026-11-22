<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\ConvertContact;
use App\Models\User;

/**
 * Contacts are kept for history: removing one deactivates it.
 */
final readonly class RemoveConvertContact
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(ConvertContact $contact, User $actor): void
    {
        if (! $contact->active) {
            return;
        }

        $contact->active = false;
        $contact->is_primary = false;
        $contact->save();

        $this->audit->record('converts.contact_removed', $contact->loadMissing('convert')->convert, newValues: [
            'contact_id' => $contact->id,
            'contact_type' => $contact->contact_type->value,
        ], actor: $actor);
    }
}
