<?php

declare(strict_types=1);

namespace App\Domain\Converts\Data;

use App\Domain\Platform\Enums\Gender;
use BackedEnum;
use Carbon\CarbonImmutable;

/**
 * Editable profile of a convert. Previous religion is applied only for
 * holders of converts.view_sensitive (UpdateConvert ignores it otherwise).
 */
final readonly class ConvertProfileData
{
    public function __construct(
        public string $fullName,
        public Gender $gender,
        public int $nationalityId,
        public ?string $nameAr = null,
        public ?string $previousName = null,
        public ?string $islamicName = null,
        public ?CarbonImmutable $dateOfBirth = null,
        public ?int $nativeLanguageId = null,
        public ?int $previousReligionId = null,
        public ?string $previousReligionOther = null,
    ) {}

    /** @param  array<string, mixed>  $state */
    public static function fromForm(array $state): self
    {
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);
        $id = static fn (mixed $value): ?int => blank($value) ? null : (int) $value;
        $gender = $state['gender'] instanceof BackedEnum ? $state['gender'] : Gender::from((string) $state['gender']);

        return new self(
            fullName: trim((string) $state['full_name']),
            gender: $gender instanceof Gender ? $gender : Gender::Male,
            nationalityId: (int) $state['nationality_id'],
            nameAr: $text($state['name_ar'] ?? null),
            previousName: $text($state['previous_name'] ?? null),
            islamicName: $text($state['islamic_name'] ?? null),
            dateOfBirth: blank($state['date_of_birth'] ?? null) ? null : CarbonImmutable::parse((string) $state['date_of_birth']),
            nativeLanguageId: $id($state['native_language_id'] ?? null),
            previousReligionId: $id($state['previous_religion_id'] ?? null),
            previousReligionOther: $text($state['previous_religion_other'] ?? null),
        );
    }
}
