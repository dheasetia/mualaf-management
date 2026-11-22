<?php

declare(strict_types=1);

namespace App\Domain\Prospects\Enums;

/**
 * 12-transactions-state-machines §12.5. `converted` is reached only through
 * RecordShahadah (step 11) and `transferred` only through a prospect transfer
 * (step 13); ChangeProspectStatus refuses both.
 */
enum ProspectStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case ActiveFollowUp = 'active_follow_up';
    case LearningIslam = 'learning_islam';
    case ConsideringIslam = 'considering_islam';
    case ReadyForShahadah = 'ready_for_shahadah';
    case Converted = 'converted';
    case NotInterested = 'not_interested';
    case LostContact = 'lost_contact';
    case Transferred = 'transferred';
    case Closed = 'closed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::NotInterested, self::LostContact, self::Closed],
            self::Contacted => [self::ActiveFollowUp, self::NotInterested, self::LostContact, self::Closed],
            self::ActiveFollowUp => [self::LearningIslam, self::ConsideringIslam, self::NotInterested, self::LostContact, self::Transferred, self::Closed],
            self::LearningIslam => [self::ConsideringIslam, self::ActiveFollowUp, self::NotInterested, self::LostContact, self::Transferred, self::Closed],
            self::ConsideringIslam => [self::ReadyForShahadah, self::LearningIslam, self::NotInterested, self::LostContact, self::Transferred, self::Closed],
            self::ReadyForShahadah => [self::Converted, self::ConsideringIslam, self::LostContact],
            self::NotInterested => [self::ActiveFollowUp, self::Closed],
            self::LostContact => [self::Contacted, self::Closed],
            self::Transferred, self::Converted, self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Targets reachable by a plain status change (not shahadah, not transfer).
     *
     * @return list<self>
     */
    public function manualTransitions(): array
    {
        return array_values(array_filter(
            $this->allowedTransitions(),
            static fn (self $target): bool => ! in_array($target, [self::Converted, self::Transferred], true),
        ));
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return __('prospects.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New, self::Contacted => 'gray',
            self::ActiveFollowUp, self::LearningIslam => 'info',
            self::ConsideringIslam, self::ReadyForShahadah => 'warning',
            self::Converted => 'success',
            self::NotInterested, self::LostContact, self::Transferred, self::Closed => 'danger',
        };
    }
}
