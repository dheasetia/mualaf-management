<?php

declare(strict_types=1);

namespace App\Domain\International\Enums;

enum ReferralStatus: string
{
    case Draft = 'draft';
    case PendingConsent = 'pending_consent';
    case Ready = 'ready';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Active = 'active';
    case Closed = 'closed';
    case Declined = 'declined';

    /**
     * 12-transactions-state-machines §12.5 (referral internasional).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingConsent, self::Ready, self::Closed],
            self::PendingConsent => [self::Ready, self::Closed],
            self::Ready => [self::Sent, self::PendingConsent, self::Closed],
            self::Sent => [self::Acknowledged, self::Declined, self::Closed],
            self::Acknowledged => [self::Active, self::Closed],
            self::Active => [self::Closed],
            self::Closed, self::Declined => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::PendingConsent => 'gray',
            self::Ready, self::Sent => 'warning',
            self::Acknowledged, self::Active => 'success',
            self::Closed, self::Declined => 'danger',
        };
    }

    public function label(): string
    {
        return __('international.referral_statuses.'.$this->value);
    }
}
