<?php

declare(strict_types=1);

namespace App\Domain\Registration\Data;

/**
 * One witness of a shahadah (open question #2: name and contact are required).
 */
final readonly class ShahadahWitnessData
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $identityLast4 = null,
        public ?string $relationship = null,
    ) {}

    /**
     * @param  array{name?: mixed, phone?: mixed, identity_last4?: mixed, relationship?: mixed}  $state
     */
    public static function fromForm(array $state): self
    {
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);

        return new self(
            name: (string) $text($state['name'] ?? null),
            phone: (string) $text($state['phone'] ?? null),
            identityLast4: $text($state['identity_last4'] ?? null),
            relationship: $text($state['relationship'] ?? null),
        );
    }
}
