<?php

declare(strict_types=1);

namespace App\Domain\International\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\DisclosureScope;
use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\HomeRelationship;
use App\Domain\International\Models\ConvertHomeCountryContact;
use App\Domain\International\Models\ConvertHomeLocation;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Home-country contacts and locations (10 §10.6), kept by those who record
 * the convert's follow-up. Contacts default to no permission to contact and
 * no disclosure of the conversion.
 */
final readonly class HomeCountryRecords
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function saveContact(Convert $convert, ?ConvertHomeCountryContact $contact, array $data, User $actor): ConvertHomeCountryContact
    {
        $this->authorize($convert, $actor, $contact);

        $contact ??= new ConvertHomeCountryContact;
        $contact->convert_id = $convert->id;
        $contact->country_id = (int) $data['country_id'];
        $contact->contact_name = (string) $data['contact_name'];
        $contact->relationship = HomeRelationship::from((string) $data['relationship']);
        $contact->phone_encrypted = PhoneNumber::normalize($data['phone'] ?? null);
        $contact->whatsapp_encrypted = PhoneNumber::normalize($data['whatsapp'] ?? null);
        $contact->email_encrypted = blank($data['email'] ?? null) ? null : mb_strtolower(trim((string) $data['email']));
        $contact->setAttribute('address', blank($data['address'] ?? null) ? null : (string) $data['address']);
        $contact->city = blank($data['city'] ?? null) ? null : (string) $data['city'];
        $contact->setAttribute('preferred_language_id', blank($data['preferred_language_id'] ?? null) ? null : (int) $data['preferred_language_id']);
        $contact->setAttribute('is_muslim', isset($data['is_muslim']) ? (bool) $data['is_muslim'] : null);
        $contact->is_primary = (bool) ($data['is_primary'] ?? false);
        $contact->permission_to_contact = (bool) ($data['permission_to_contact'] ?? false);
        $contact->disclosure_scope = DisclosureScope::tryFrom((string) ($data['disclosure_scope'] ?? '')) ?? DisclosureScope::None;
        $contact->setAttribute('notes', blank($data['notes'] ?? null) ? null : (string) $data['notes']);
        $contact->created_by ??= $actor->id;
        $this->saveAndAudit($contact, $convert, 'home_contact.saved', $actor);

        return $contact;
    }

    /** @param  array<string, mixed>  $data */
    public function saveLocation(Convert $convert, ?ConvertHomeLocation $location, array $data, User $actor): ConvertHomeLocation
    {
        $this->authorize($convert, $actor, $location);

        $location ??= new ConvertHomeLocation;
        $location->convert_id = $convert->id;
        $location->country_id = (int) $data['country_id'];

        foreach (['province_state', 'city', 'district', 'village', 'address', 'latitude', 'longitude', 'google_maps_url', 'notes'] as $field) {
            $location->setAttribute($field, blank($data[$field] ?? null) ? null : (string) $data[$field]);
        }

        $location->is_primary = (bool) ($data['is_primary'] ?? false);
        $location->created_by ??= $actor->id;
        $this->saveAndAudit($location, $convert, 'home_location.saved', $actor);

        return $location;
    }

    private function saveAndAudit(Model $record, Convert $convert, string $action, User $actor): void
    {
        $record->save();
        $this->audit->recordChanges($action, $record, actor: $actor);

        if ((bool) $record->getAttribute('is_primary')) {
            $record->newQuery()->where('convert_id', $convert->id)->whereKeyNot($record->getKey())->update(['is_primary' => false]);
        }
    }

    private function authorize(Convert $convert, User $actor, ?Model $record): void
    {
        if (! $actor->can('recordFollowUp', $convert) || ($record !== null && (int) $record->getAttribute('convert_id') !== $convert->id)) {
            throw new AuthorizationException;
        }
    }
}
