<?php

declare(strict_types=1);

namespace App\Domain\Converts\Data;

use App\Domain\Converts\Enums\IdentityType;
use Carbon\CarbonImmutable;

final readonly class IdentifierData
{
    /**
     * @param  int|null  $issuingCountryId  ignored for types the Kingdom issues (always SA)
     */
    public function __construct(
        public IdentityType $type,
        public string $number,
        public ?int $issuingCountryId = null,
        public ?CarbonImmutable $expiresOn = null,
    ) {}
}
