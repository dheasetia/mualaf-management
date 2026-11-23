<?php

declare(strict_types=1);

namespace App\Domain\International\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\SharedField;
use App\Domain\International\Models\ConvertHomeCountryContact;
use App\Domain\International\Models\ConvertHomeLocation;
use App\Domain\International\Models\InternationalReferral;
use App\Domain\Registration\Services\ShahadahDirectory;

/**
 * What a referral shares with the receiving organization: only the fields
 * in its information_scope (which the consent covers). Contacts only when
 * they agreed to be contacted.
 */
final readonly class ReferralPacket
{
    public function __construct(private ShahadahDirectory $shahadah) {}

    /** @return array<string, string|null> */
    public function for(InternationalReferral $referral): array
    {
        $convert = Convert::query()->findOrFail($referral->convert_id);
        $packet = [];

        foreach ($referral->information_scope as $field) {
            $packet[$field] = match (SharedField::tryFrom($field)) {
                SharedField::Name => $convert->full_name,
                SharedField::IslamicName => $convert->islamic_name,
                SharedField::Gender => $convert->gender->label(),
                SharedField::ShahadahDate => $this->shahadahDate($convert->id),
                SharedField::Contact => $this->contact($convert->id),
                SharedField::HomeCity => ConvertHomeLocation::query()->where('convert_id', $convert->id)->orderByDesc('is_primary')->value('city'),
                SharedField::GuidanceStatus, SharedField::Languages, SharedField::SupportNeeded, null => null,
            };
        }

        return $packet;
    }

    private function shahadahDate(int $convertId): ?string
    {
        $shahadah = $this->shahadah->verifiedFirst($convertId);

        return $shahadah === null ? null : $shahadah['date']->toDateString();
    }

    private function contact(int $convertId): ?string
    {
        $contact = ConvertHomeCountryContact::query()->where('convert_id', $convertId)->where('permission_to_contact', true)->orderByDesc('is_primary')->first();

        return $contact === null ? null : trim($contact->contact_name.' '.($contact->whatsapp_encrypted ?? $contact->phone_encrypted ?? ''));
    }
}
