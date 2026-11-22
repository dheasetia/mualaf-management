<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\ContactType;
use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Models\User;
use App\Support\Identity\Fingerprint;
use App\Support\Identity\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Adds an encrypted contact; phones are stored normalised. A new primary
 * contact replaces the previous primary.
 */
final readonly class AddConvertContact
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Convert $convert, ContactType $type, string $value, User $actor, bool $primary = false): ConvertContact
    {
        if ($convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        [$stored, $fingerprint] = $this->normalize($type, $value);

        return DB::transaction(function () use ($convert, $type, $stored, $fingerprint, $actor, $primary): ConvertContact {
            if ($primary) {
                ConvertContact::query()->where('convert_id', $convert->id)->where('is_primary', true)->update(['is_primary' => false]);
            }

            $contact = new ConvertContact;
            $contact->convert_id = $convert->id;
            $contact->contact_type = $type;
            $contact->value_encrypted = $stored;
            $contact->value_fingerprint = $fingerprint;
            $contact->is_primary = $primary;
            $contact->created_by = $actor->id;
            $contact->save();

            $this->audit->record('converts.contact_added', $convert, newValues: [
                'contact_id' => $contact->id,
                'contact_type' => $type->value,
                'is_primary' => $primary,
            ], actor: $actor);

            return $contact;
        });
    }

    /** @return array{0: string, 1: string} stored value and fingerprint */
    private function normalize(ContactType $type, string $value): array
    {
        $value = trim($value);

        if ($type->isPhone()) {
            $normalized = PhoneNumber::normalize($value) ?? throw ConvertRuleViolation::invalidContact();

            return ['+'.$normalized, Fingerprint::make('phone', $normalized)];
        }

        if ($type === ContactType::Email) {
            $normalized = mb_strtolower($value);

            if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
                throw ConvertRuleViolation::invalidContact();
            }

            return [$normalized, Fingerprint::make('email', $normalized)];
        }

        if ($value === '') {
            throw ConvertRuleViolation::invalidContact();
        }

        return [$value, Fingerprint::make($type->value, mb_strtolower($value))];
    }
}
