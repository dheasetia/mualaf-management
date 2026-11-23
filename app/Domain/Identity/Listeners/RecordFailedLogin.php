<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Notifications\AccountLockedNotification;
use App\Domain\Identity\Services\AccountLockout;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

final readonly class RecordFailedLogin
{
    public function __construct(private AccountLockout $lockout, private AuditLogger $audit) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return;
        }

        $justLocked = $this->lockout->recordFailure($email);
        $user = $event->user instanceof User ? $event->user : null;

        // The attempted email is not stored: for unknown accounts it may be
        // anything a visitor typed. Known accounts are the subject instead.
        $this->audit->record(
            'auth.login_failed',
            $user,
            newValues: ['guard' => $event->guard, 'locked' => $justLocked],
            actorType: ActorType::User,
        );

        if ($justLocked && $user !== null) {
            $user->notify(new AccountLockedNotification(request()->ip()));
        }
    }
}
