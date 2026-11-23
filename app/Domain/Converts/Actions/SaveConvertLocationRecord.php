<?php

declare(strict_types=1);

namespace App\Domain\Converts\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Exceptions\ConvertRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertEmployment;
use App\Domain\Converts\Models\ConvertResidence;
use App\Models\User;
use App\Support\Geo\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a convert's employment, residence or contact person
 * (06-data-model-core §6.9) from validated form state. Only whitelisted
 * fields are written; coordinates come from the typed values or the Google
 * Maps URL; a new current employment or residence ends the previous one,
 * and a new primary contact person replaces the previous primary.
 */
final readonly class SaveConvertLocationRecord
{
    /** @var array<class-string<Model>, list<string>> */
    private const array FIELDS = [
        ConvertEmployment::class => ['company_id', 'company_name_free', 'employee_number', 'occupation', 'department', 'work_location_description', 'work_city_id', 'work_phone_encrypted', 'employment_status', 'started_at', 'ended_at', 'is_current'],
        ConvertResidence::class => ['residence_type', 'name', 'city_id', 'address', 'district', 'building_no', 'room_no', 'is_pickup_point', 'pickup_instructions', 'started_at', 'ended_at', 'is_current', 'notes'],
        ConvertContactPerson::class => ['company_id', 'residence_id', 'name', 'position', 'contact_role', 'phone_encrypted', 'alternate_phone_encrypted', 'email', 'preferred_language_id', 'permission_to_contact', 'disclosure_scope', 'is_primary', 'notes'],
    ];

    /** Columns holding the location, per record type: [latitude, longitude, url]. */
    private const array LOCATION = [
        ConvertEmployment::class => ['work_latitude', 'work_longitude', 'work_google_maps_url'],
        ConvertResidence::class => ['latitude', 'longitude', 'google_maps_url'],
    ];

    /** Flag that only one row per convert may hold. */
    private const array EXCLUSIVE = [
        ConvertEmployment::class => 'is_current',
        ConvertResidence::class => 'is_current',
        ConvertContactPerson::class => 'is_primary',
    ];

    private const array AUDIT_ACTION = [
        ConvertEmployment::class => 'converts.employment_saved',
        ConvertResidence::class => 'converts.residence_saved',
        ConvertContactPerson::class => 'converts.contact_person_saved',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @template TRecord of ConvertEmployment|ConvertResidence|ConvertContactPerson
     *
     * @param  class-string<TRecord>  $type
     * @param  TRecord|null  $record
     * @param  array<string, mixed>  $data
     * @return TRecord
     */
    public function handle(Convert $convert, string $type, ?Model $record, array $data, User $actor): Model
    {
        if ($convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        if ($record !== null && $record->getAttribute('convert_id') !== $convert->id) {
            throw ConvertRuleViolation::closed();
        }

        $location = isset(self::LOCATION[$type])
            ? Location::resolve(
                $data[self::LOCATION[$type][0]] ?? null,
                $data[self::LOCATION[$type][1]] ?? null,
                $data[self::LOCATION[$type][2]] ?? null,
                ConvertRuleViolation::invalidMapsUrl(...),
            )
            : null;

        return DB::transaction(function () use ($convert, $type, $record, $data, $actor, $location): Model {
            $record ??= new $type;
            $isNew = ! $record->exists;

            foreach (self::FIELDS[$type] as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    $record->setAttribute($field, is_string($value) && trim($value) === '' ? null : $value);
                }
            }

            if ($location !== null) {
                [$latitude, $longitude, $url] = self::LOCATION[$type];
                $record->setAttribute($latitude, $location['latitude']);
                $record->setAttribute($longitude, $location['longitude']);
                $record->setAttribute($url, $location['google_maps_url']);
            }

            if ($isNew) {
                $record->setAttribute('convert_id', $convert->id);
                $record->setAttribute('created_by', $actor->id);
            }

            $flag = self::EXCLUSIVE[$type];

            if ((bool) $record->getAttribute($flag)) {
                $type::query()->where('convert_id', $convert->id)
                    ->when(! $isNew, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->update([$flag => false]);
            }

            $changes = array_keys($record->getDirty());
            $record->save();

            $this->audit->record(self::AUDIT_ACTION[$type], $convert, newValues: [
                'record_id' => $record->getKey(),
                'created' => $isNew,
                'fields' => $changes,
            ], actor: $actor);

            return $record;
        });
    }

    public function deleteContactPerson(Convert $convert, ConvertContactPerson $person, User $actor): void
    {
        if ($person->convert_id !== $convert->id || $convert->status->isTerminal()) {
            throw ConvertRuleViolation::closed();
        }

        $person->delete();

        $this->audit->record('converts.contact_person_deleted', $convert, newValues: ['record_id' => $person->id], actor: $actor);
    }
}
