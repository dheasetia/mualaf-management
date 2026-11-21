<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Notifications\AccountLockedNotification;
use App\Domain\Identity\Services\AccountLockout;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

final readonly class RecordFailedLogin
{
    public function __construct(private AccountLockout $lockout) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return;
        }

        $justLocked = $this->lockout->recordFailure($email);

        if ($justLocked && $event->user instanceof User) {
            $event->user->notify(new AccountLockedNotification(request()->ip()));
        }
    }
}
