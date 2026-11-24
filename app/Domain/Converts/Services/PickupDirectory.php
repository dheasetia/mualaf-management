<?php

declare(strict_types=1);

namespace App\Domain\Converts\Services;

use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Converts\Models\ConvertEmployment;
use App\Domain\Converts\Models\ConvertResidence;
use App\Models\User;
use App\Support\Localization\LocalizedName;

/**
 * What transport may know about a convert (08 §8.7, 04 §4.4): pickup points
 * from the convert's residences or current workplace, and contact persons
 * who agreed to be contacted. The pickup sheet gets a nickname only, never
 * religion or shahadah details.
 */
final readonly class PickupDirectory
{
    public function __construct(private ConvertAccess $access) {}

    /**
     * How a pickup lists the convert to this user: the usual display name for
     * those who may see the convert, else the nickname, or the alias for a
     * high-confidentiality convert.
     */
    public function label(User $user, int $convertId): string
    {
        $convert = Convert::query()->find($convertId);

        if ($convert === null) {
            return '—';
        }

        if ($this->access->canSee($user, $convert)) {
            return $this->access->displayName($user, $convert);
        }

        return $convert->disclosure_risk_level === DisclosureLevel::HighConfidentiality ? $convert->alias() : $this->sheetEntry($convertId, null)['nickname'];
    }

    /**
     * A residence of this convert as a pickup point, or null.
     *
     * @return array{residence_id: int, latitude: string|null, longitude: string|null, address: string}|null
     */
    public function residencePoint(int $convertId, int $residenceId): ?array
    {
        $residence = ConvertResidence::query()->with('city')->where('convert_id', $convertId)->find($residenceId);

        return $residence === null ? null : [
            'residence_id' => $residence->id,
            'latitude' => $residence->latitude,
            'longitude' => $residence->longitude,
            'address' => trim(implode(', ', array_filter([$residence->name, LocalizedName::of($residence->city), $residence->google_maps_url]))),
        ];
    }

    /**
     * The current workplace as a pickup point, or null when it has no location.
     *
     * @return array{latitude: string, longitude: string, address: string}|null
     */
    public function workplacePoint(int $convertId): ?array
    {
        $job = ConvertEmployment::query()->where('convert_id', $convertId)->where('is_current', true)
            ->whereNotNull('work_latitude')->whereNotNull('work_longitude')->latest('id')->first();

        return $job === null ? null : [
            'latitude' => (string) $job->work_latitude,
            'longitude' => (string) $job->work_longitude,
            'address' => (string) ($job->work_google_maps_url ?? ''),
        ];
    }

    /** A contact person of this convert who agreed to be contacted. */
    public function isContactable(int $convertId, int $contactPersonId): bool
    {
        return ConvertContactPerson::query()->where('convert_id', $convertId)->whereKey($contactPersonId)->where('permission_to_contact', true)->exists();
    }

    /** The residence to fetch the convert from: the marked pickup point, else the current one. */
    public function defaultResidenceId(int $convertId): ?int
    {
        $id = ConvertResidence::query()->where('convert_id', $convertId)->where(fn ($query) => $query->where('is_pickup_point', true)->orWhere('is_current', true))
            ->orderByDesc('is_pickup_point')->orderByDesc('is_current')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<int, string> */
    public function residenceOptions(int $convertId): array
    {
        return ConvertResidence::query()->where('convert_id', $convertId)->orderByDesc('is_pickup_point')->orderByDesc('is_current')
            ->get()->mapWithKeys(fn (ConvertResidence $residence): array => [$residence->id => (string) ($residence->name ?? $residence->residence_type->label())])->all();
    }

    /** @return array<int, string> */
    public function contactOptions(int $convertId): array
    {
        return ConvertContactPerson::query()->where('convert_id', $convertId)->where('permission_to_contact', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Pickup sheet line for a convert: nickname and contact, plus whether the
     * convert is high-confidentiality (then the line needs Head of Dawah's
     * approval before it is printed).
     *
     * @return array{nickname: string, contact_name: string|null, contact_phone: string|null, high_confidentiality: bool}
     */
    public function sheetEntry(int $convertId, ?int $contactPersonId): array
    {
        $convert = Convert::query()->findOrFail($convertId);
        $contact = $contactPersonId === null ? null
            : ConvertContactPerson::query()->where('convert_id', $convertId)->where('permission_to_contact', true)->find($contactPersonId);
        $words = preg_split('/\s+/u', trim($convert->full_name)) ?: [];

        return [
            'nickname' => $convert->islamic_name ?? (string) ($words[0] ?? $convert->initials()),
            'contact_name' => $contact?->name,
            'contact_phone' => $contact?->phone_encrypted,
            'high_confidentiality' => $convert->disclosure_risk_level === DisclosureLevel::HighConfidentiality,
        ];
    }

    public function isHighConfidentiality(int $convertId): bool
    {
        return Convert::query()->whereKey($convertId)->where('disclosure_risk_level', DisclosureLevel::HighConfidentiality)->exists();
    }
}
