<?php

declare(strict_types=1);

namespace App\Domain\Dai\Data;

use App\Domain\Dai\Enums\EmploymentStatus;
use App\Domain\Dai\Enums\LanguageProficiency;
use App\Domain\Platform\Enums\Gender;
use Carbon\CarbonImmutable;

/**
 * Profile fields of a da'i as entered by an office; status changes go through ChangeDaiStatus.
 */
final readonly class DaiData
{
    /**
     * @param  list<string>  $specialties
     * @param  array<int, LanguageProficiency>  $languages  language_id => proficiency
     */
    public function __construct(
        public string $employeeNumber,
        public string $fullName,
        public ?string $fullNameAr,
        public Gender $gender,
        public EmploymentStatus $employmentStatus,
        public ?int $nationalityId = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $education = null,
        public array $specialties = [],
        public ?int $supervisorDaiId = null,
        public ?CarbonImmutable $joinedAt = null,
        public ?int $userId = null,
        public array $languages = [],
    ) {}

    /**
     * From panel form state: `language_rows` is a repeater of {language_id, proficiency}.
     *
     * @param  array<string, mixed>  $state
     */
    public static function fromForm(array $state): self
    {
        $languages = [];

        foreach ((array) ($state['language_rows'] ?? []) as $row) {
            if (is_array($row) && filled($row['language_id'] ?? null) && filled($row['proficiency'] ?? null)) {
                $languages[(int) $row['language_id']] = self::enum(LanguageProficiency::class, $row['proficiency']);
            }
        }

        $nullable = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);
        $nullableId = static fn (mixed $value): ?int => blank($value) ? null : (int) $value;

        return new self(
            employeeNumber: trim((string) $state['employee_number']),
            fullName: trim((string) $state['full_name']),
            fullNameAr: $nullable($state['full_name_ar'] ?? null),
            gender: self::enum(Gender::class, $state['gender']),
            employmentStatus: self::enum(EmploymentStatus::class, $state['employment_status']),
            nationalityId: $nullableId($state['nationality_id'] ?? null),
            phone: $nullable($state['phone'] ?? null),
            email: $nullable($state['email'] ?? null),
            education: $nullable($state['education'] ?? null),
            specialties: array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                (array) ($state['specialties'] ?? []),
            ), static fn (string $value): bool => $value !== '')),
            supervisorDaiId: $nullableId($state['supervisor_dai_id'] ?? null),
            joinedAt: blank($state['joined_at'] ?? null) ? null : CarbonImmutable::parse((string) $state['joined_at']),
            userId: $nullableId($state['user_id'] ?? null),
            languages: $languages,
        );
    }

    /**
     * Form state holds either the enum or its backing value.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    private static function enum(string $enum, mixed $value): \BackedEnum
    {
        return $value instanceof $enum ? $value : $enum::from((string) $value);
    }
}
