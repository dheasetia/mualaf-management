<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Locks an account (by login email) after repeated failed sign-ins
 * (13-infrastructure-security §13.5: 5 failures → 15 minutes).
 *
 * Applied to every submitted email, existing or not, so the response does not
 * reveal which emails have accounts.
 */
final class AccountLockout
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 900;

    public function isLocked(string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->key($email), self::MAX_ATTEMPTS);
    }

    /**
     * Records a failure. Returns true when this failure locks the account.
     */
    public function recordFailure(string $email): bool
    {
        RateLimiter::hit($this->key($email), self::DECAY_SECONDS);

        return RateLimiter::attempts($this->key($email)) === self::MAX_ATTEMPTS;
    }

    public function secondsUntilUnlocked(string $email): int
    {
        return RateLimiter::availableIn($this->key($email));
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->key($email));
    }

    private function key(string $email): string
    {
        return 'login-lockout:'.hash('sha256', mb_strtolower(trim($email)));
    }
}
