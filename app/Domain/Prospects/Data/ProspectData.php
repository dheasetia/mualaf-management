<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Data;

use App\Domain\Platform\Enums\Gender;
use App\Domain\Prospects\Enums\InterestLevel;
use App\Domain\Prospects\Enums\ProspectSource;
use BackedEnum;

/**
 * Profile of a prospect as entered by the office. Status and primary da'i
 * change through their own actions (only CreateProspect takes the first da'i).
 */
final readonly class ProspectData
{
    public function __construct(
        public string $fullName,
        public Gender $gender,
        public ProspectSource $source,
        public ?int $nationalityId = null,
        public ?int $nativeLanguageId = null,
        public ?int $currentReligionId = null,
        public ?string $currentReligionOther = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $occupation = null,
        public ?int $companyId = null,
        public ?int $residenceCityId = null,
        public ?string $residenceDescription = null,
        public ?string $sourceDescription = null,
        public ?InterestLevel $interestLevel = null,
        public ?int $primaryDaiId = null,
        public bool $confirmedPossibleDuplicate = false,
    ) {}

    /** @param  array<string, mixed>  $state */
    public static function fromForm(array $state): self
    {
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);
        $id = static fn (mixed $value): ?int => blank($value) ? null : (int) $value;
        $enum = static fn (string $class, mixed $value): ?BackedEnum => blank($value) ? null
            : ($value instanceof $class ? $value : $class::from((string) $value));

        $gender = $enum(Gender::class, $state['gender'] ?? null);
        $source = $enum(ProspectSource::class, $state['source_type'] ?? null);
        $interest = $enum(InterestLevel::class, $state['interest_level'] ?? null);

        return new self(
            fullName: trim((string) $state['full_name']),
            gender: $gender instanceof Gender ? $gender : Gender::Male,
            source: $source instanceof ProspectSource ? $source : ProspectSource::Other,
            nationalityId: $id($state['nationality_id'] ?? null),
            nativeLanguageId: $id($state['native_language_id'] ?? null),
            currentReligionId: $id($state['current_religion_id'] ?? null),
            currentReligionOther: $text($state['current_religion_other'] ?? null),
            phone: $text($state['phone'] ?? null),
            email: $text($state['email'] ?? null),
            occupation: $text($state['occupation'] ?? null),
            companyId: $id($state['company_id'] ?? null),
            residenceCityId: $id($state['residence_city_id'] ?? null),
            residenceDescription: $text($state['residence_description'] ?? null),
            sourceDescription: $text($state['source_description'] ?? null),
            interestLevel: $interest instanceof InterestLevel ? $interest : null,
            primaryDaiId: $id($state['primary_dai_id'] ?? null),
            confirmedPossibleDuplicate: (bool) ($state['confirm_possible_duplicate'] ?? false),
        );
    }
}
