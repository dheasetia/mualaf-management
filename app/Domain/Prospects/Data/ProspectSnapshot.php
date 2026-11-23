<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Data;

use App\Domain\Platform\Enums\Gender;

/**
 * What the shahadah registration takes over from a prospect.
 */
final readonly class ProspectSnapshot
{
    public function __construct(
        public int $id,
        public string $fullName,
        public Gender $gender,
        public int $primaryDaiId,
        public ?int $nationalityId,
        public ?int $nativeLanguageId,
        public ?int $currentReligionId,
        public ?string $currentReligionOther,
        public ?string $phone,
        public ?string $email,
    ) {}
}
