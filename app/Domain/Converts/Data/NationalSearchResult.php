<?php

declare(strict_types=1);

namespace App\Domain\Converts\Data;

/**
 * What a national identity search may disclose (03-tenancy-authorization §3.4):
 * whether the identity is registered and where, and a name only as far as the
 * convert's disclosure level and the searcher's permissions allow. Never
 * shahadah date, contacts, address or previous religion.
 */
final readonly class NationalSearchResult
{
    public function __construct(
        public bool $found,
        public bool $inOwnOffice = false,
        public ?string $officeCode = null,
        public ?string $officeName = null,
        public ?string $displayName = null,
        public bool $withheld = false,
    ) {}

    public static function notFound(): self
    {
        return new self(found: false);
    }

    /** @return array{found: bool, inOwnOffice: bool, officeCode: ?string, officeName: ?string, displayName: ?string, withheld: bool} */
    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'inOwnOffice' => $this->inOwnOffice,
            'officeCode' => $this->officeCode,
            'officeName' => $this->officeName,
            'displayName' => $this->displayName,
            'withheld' => $this->withheld,
        ];
    }
}
