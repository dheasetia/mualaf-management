<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Platform\Models\Country;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Data\ShahadahWitnessData;
use App\Domain\Registration\Exceptions\ImportRuleViolation;
use App\Domain\Registration\Models\ImportRow;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The spreadsheet layout of a historical convert import and the mapping of
 * one row to a shahadah registration. Every check that needs no other
 * office's data runs here, so a row is either valid or carries the reason
 * it is not; the national duplicate check runs when the row is imported.
 */
final readonly class HistoricalImportRows
{
    /** Required columns are marked with true. */
    public const array COLUMNS = [
        'full_name' => true,
        'name_ar' => false,
        'islamic_name' => false,
        'previous_name' => false,
        'gender' => true,
        'date_of_birth' => false,
        'nationality' => true,
        'identity_type' => true,
        'identity_number' => true,
        'identity_country' => false,
        'phone' => false,
        'email' => false,
        'shahadah_date' => true,
        'shahadah_location' => true,
        'primary_dai' => true,
        'witness_1_name' => true,
        'witness_1_phone' => true,
        'witness_2_name' => true,
        'witness_2_phone' => true,
        'witness_3_name' => false,
        'witness_3_phone' => false,
        'notes' => false,
    ];

    /** An example row for the template; identity numbers are placeholders. */
    public const array EXAMPLE = [
        'full_name' => 'Jose Santos',
        'name_ar' => '',
        'islamic_name' => 'Yusuf',
        'previous_name' => '',
        'gender' => 'male',
        'date_of_birth' => '1990-05-14',
        'nationality' => 'PH',
        'identity_type' => 'iqamah',
        'identity_number' => '2xxxxxxxxx',
        'identity_country' => '',
        'phone' => '0551234567',
        'email' => '',
        'shahadah_date' => '2019-03-08',
        'shahadah_location' => 'Islamic Center',
        'primary_dai' => 'EMP-001',
        'witness_1_name' => 'Ahmad Ali',
        'witness_1_phone' => '0550000001',
        'witness_2_name' => 'Omar Hassan',
        'witness_2_phone' => '0550000002',
        'witness_3_name' => '',
        'witness_3_phone' => '',
        'notes' => '',
    ];

    public function __construct(private IdentityRegistry $identities, private ShahadahWitnesses $witnesses, private DaiDirectory $dais) {}

    /** @return list<string> */
    public static function requiredColumns(): array
    {
        return array_keys(array_filter(self::COLUMNS));
    }

    /**
     * The registration this row describes, for the office in context.
     *
     * @throws ImportRuleViolation|Throwable on the first problem found
     */
    public function toRegistration(ImportRow $row): ShahadahRegistrationData
    {
        foreach (self::requiredColumns() as $column) {
            if ($row->cell($column) === null) {
                throw ImportRuleViolation::missingCell($column);
            }
        }

        $identityType = IdentityType::tryFrom(mb_strtolower((string) $row->cell('identity_type')))
            ?? throw ImportRuleViolation::invalidCell('identity_type');
        $identityCountry = $row->cell('identity_country');
        $identity = new IdentifierData(
            $identityType,
            (string) $row->cell('identity_number'),
            $identityCountry === null ? null : $this->countryId($identityCountry, 'identity_country'),
        );

        // Validates the number for its type and the issuing country.
        $this->identities->fingerprint($identity);

        $witnesses = [];

        foreach ([1, 2, 3] as $index) {
            $name = $row->cell("witness_{$index}_name");
            $phone = $row->cell("witness_{$index}_phone");

            if ($name !== null || $phone !== null) {
                $witnesses[] = new ShahadahWitnessData((string) $name, (string) $phone);
            }
        }

        $data = new ShahadahRegistrationData(
            profile: new ConvertProfileData(
                fullName: (string) $row->cell('full_name'),
                gender: Gender::tryFrom(mb_strtolower((string) $row->cell('gender'))) ?? throw ImportRuleViolation::invalidCell('gender'),
                nationalityId: $this->countryId((string) $row->cell('nationality'), 'nationality'),
                nameAr: $row->cell('name_ar'),
                previousName: $row->cell('previous_name'),
                islamicName: $row->cell('islamic_name'),
                dateOfBirth: $this->date($row->cell('date_of_birth'), 'date_of_birth'),
            ),
            identity: $identity,
            shahadahDate: $this->date($row->cell('shahadah_date'), 'shahadah_date') ?? throw ImportRuleViolation::missingCell('shahadah_date'),
            location: (string) $row->cell('shahadah_location'),
            primaryDaiId: $this->daiId((string) $row->cell('primary_dai')),
            notes: $row->cell('notes'),
            phone: $row->cell('phone'),
            email: $this->email($row->cell('email')),
            witnesses: $witnesses,
            importRowId: $row->id,
        );

        $this->witnesses->normalize($data->witnesses);

        if ($data->shahadahDate->isAfter(CarbonImmutable::now((string) config('app.display_timezone'))->endOfDay())) {
            throw ImportRuleViolation::invalidCell('shahadah_date');
        }

        return $data;
    }

    /** The identity fingerprint of a row, to find the same person twice in one file. */
    public function identityKey(ShahadahRegistrationData $data): string
    {
        return $this->identities->fingerprint($data->identity)['fingerprint'];
    }

    private function countryId(string $iso2, string $column): int
    {
        $id = Country::query()->where('iso2', mb_strtoupper($iso2))->value('id');

        return $id === null ? throw ImportRuleViolation::invalidCell($column) : (int) $id;
    }

    private function date(?string $value, string $column): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ImportRuleViolation::invalidCell($column);
        }

        return $date;
    }

    /** An active da'i of the office in context, by employee number. */
    private function daiId(string $employeeNumber): int
    {
        return $this->dais->activeIdByEmployeeNumber($employeeNumber) ?? throw ImportRuleViolation::invalidCell('primary_dai');
    }

    private function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? throw ImportRuleViolation::invalidCell('email') : $value;
    }
}
