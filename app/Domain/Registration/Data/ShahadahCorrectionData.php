<?php

declare(strict_types=1);

namespace App\Domain\Registration\Data;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use Carbon\CarbonImmutable;

/**
 * Input of CorrectShahadahRegistration. A null identity keeps the current
 * number (the Registration Officer cannot see it in full); a given one
 * replaces it.
 */
final readonly class ShahadahCorrectionData
{
    /** @param  list<ShahadahWitnessData>  $witnesses */
    public function __construct(
        public ConvertProfileData $profile,
        public CarbonImmutable $shahadahDate,
        public string $location,
        public array $witnesses,
        public ?IdentifierData $identity = null,
        public ?string $shahadahTime = null,
        public ?int $cityId = null,
        public ?int $preacherDaiId = null,
        public ?string $notes = null,
    ) {}
}
