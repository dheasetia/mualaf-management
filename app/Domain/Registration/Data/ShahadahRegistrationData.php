<?php

declare(strict_types=1);

namespace App\Domain\Registration\Data;

use App\Domain\Converts\Data\ConvertProfileData;
use App\Domain\Converts\Data\IdentifierData;
use Carbon\CarbonImmutable;

/**
 * Input of RecordShahadah. With a prospect, the primary da'i and contacts come
 * from the prospect; a walk-in needs the primary da'i chosen on the form.
 */
final readonly class ShahadahRegistrationData
{
    public function __construct(
        public ConvertProfileData $profile,
        public IdentifierData $identity,
        public CarbonImmutable $shahadahDate,
        public string $location,
        public ?int $prospectId = null,
        public ?int $primaryDaiId = null,
        public ?string $shahadahTime = null,
        public ?int $cityId = null,
        public ?int $preacherDaiId = null,
        public ?string $notes = null,
        public ?string $phone = null,
        public ?string $email = null,
        /** @var list<ShahadahWitnessData> */
        public array $witnesses = [],
    ) {}
}
