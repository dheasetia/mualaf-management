<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Enums;

enum RegistrationStatus: string
{
    case Applied = 'applied';
    case EligibilityChecked = 'eligibility_checked';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case Approved = 'approved';
    case Waitlisted = 'waitlisted';
    case Confirmed = 'confirmed';
    case Departed = 'departed';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Applied, self::EligibilityChecked => [self::EligibilityChecked, self::Eligible, self::Ineligible, self::Withdrawn],
            self::Eligible => [self::Approved, self::Waitlisted, self::Withdrawn],
            self::Waitlisted => [self::Approved, self::Withdrawn],
            self::Approved => [self::Confirmed, self::Withdrawn],
            self::Confirmed => [self::Departed, self::Withdrawn],
            self::Departed => [self::Completed],
            self::Ineligible, self::Completed, self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Holds one of the program's seats. */
    public function takesSeat(): bool
    {
        return in_array($this, [self::Approved, self::Confirmed, self::Departed, self::Completed], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Applied, self::EligibilityChecked => 'gray',
            self::Eligible, self::Waitlisted => 'warning',
            self::Approved, self::Confirmed, self::Departed => 'info',
            self::Completed => 'success',
            self::Ineligible, self::Withdrawn => 'danger',
        };
    }

    public function label(): string
    {
        return __('pilgrimage.registration_statuses.'.$this->value);
    }
}
