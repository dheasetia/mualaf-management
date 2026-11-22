<?php

declare(strict_types=1);

namespace App\Domain\Dai\Data;

use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use BackedEnum;
use Carbon\CarbonImmutable;

final readonly class FollowUpData
{
    public function __construct(
        public FollowUpType $type,
        public ContactMethod $contactMethod,
        public CarbonImmutable $occurredAt,
        public string $summary,
        public Confidentiality $confidentiality = Confidentiality::Normal,
        public ?int $durationMinutes = null,
        public ?string $location = null,
        public ?string $questionsRaised = null,
        public ?string $outcome = null,
        public ?string $nextAction = null,
        public ?CarbonImmutable $nextFollowUpAt = null,
    ) {}

    /** @param  array<string, mixed>  $state */
    public static function fromForm(array $state): self
    {
        $text = static fn (mixed $value): ?string => blank($value) ? null : trim((string) $value);

        return new self(
            type: self::enum(FollowUpType::class, $state['follow_up_type']),
            contactMethod: self::enum(ContactMethod::class, $state['contact_method']),
            occurredAt: CarbonImmutable::parse((string) $state['occurred_at']),
            summary: trim((string) $state['summary']),
            confidentiality: self::enum(Confidentiality::class, $state['confidentiality_level'] ?? Confidentiality::Normal),
            durationMinutes: blank($state['duration_minutes'] ?? null) ? null : (int) $state['duration_minutes'],
            location: $text($state['location'] ?? null),
            questionsRaised: $text($state['questions_raised'] ?? null),
            outcome: $text($state['outcome'] ?? null),
            nextAction: $text($state['next_action'] ?? null),
            nextFollowUpAt: blank($state['next_follow_up_at'] ?? null) ? null : CarbonImmutable::parse((string) $state['next_follow_up_at']),
        );
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    private static function enum(string $enum, mixed $value): BackedEnum
    {
        return $value instanceof $enum ? $value : $enum::from((string) $value);
    }
}
