<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Registration\Services\ShahadahDirectory;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Dates\HijriDate;
use Carbon\CarbonImmutable;

/**
 * What a letter prints, frozen at generation (BR-017): later edits of the
 * convert, office or template never change an issued letter.
 */
final readonly class LetterSnapshotBuilder
{
    public function __construct(private ShahadahDirectory $shahadah) {}

    /**
     * @param  array{addressee?: string|null, body?: string|null}  $input
     * @return array<string, string|null>
     */
    public function build(string $number, LetterType $type, Convert $convert, Tenant $tenant, CarbonImmutable $issuedAt, array $input): array
    {
        $shahadah = $this->shahadah->verifiedFirst($convert->id);
        $nationality = $convert->nationality()->first();

        return [
            'document_number' => $number,
            'letter_type_ar' => __('documents.letter_types.'.$type->value, [], 'ar'),
            'full_name' => $convert->full_name,
            'name_ar' => $convert->name_ar,
            'islamic_name' => $convert->islamic_name,
            'previous_name' => $convert->previous_name,
            'gender_ar' => __('platform.genders.'.$convert->gender->value, [], 'ar'),
            'nationality_ar' => $nationality?->getAttribute('name_ar'),
            'date_of_birth' => $convert->date_of_birth?->toDateString(),
            'shahadah_date' => $shahadah === null ? null : $shahadah['date']->toDateString(),
            'shahadah_date_hijri_ar' => $shahadah === null ? null : HijriDate::format($shahadah['date']),
            'shahadah_date_gregorian_ar' => $shahadah === null ? null : HijriDate::gregorian($shahadah['date']),
            'office_name_ar' => $tenant->name_ar,
            'office_name_en' => $tenant->name_en,
            'director_name' => $tenant->getAttribute('director_name'),
            'addressee' => $input['addressee'] ?? null,
            'body' => $input['body'] ?? null,
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri_ar' => HijriDate::format($issuedAt),
            'issued_on_gregorian_ar' => HijriDate::gregorian($issuedAt),
        ];
    }
}
