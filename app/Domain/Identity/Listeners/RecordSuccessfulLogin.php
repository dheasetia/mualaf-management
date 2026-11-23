<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Services\AccountLockout;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;

final readonly class RecordSuccessfulLogin
{
    public function __construct(private AccountLockout $lockout, private AuditLogger $audit) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $this->lockout->clear($user->email);

        // Direct update: a login is not a profile change and must not fire model events.
        User::query()->whereKey($user->id)->update([
            'last_login_at' => CarbonImmutable::now(),
            'last_login_ip' => request()->ip(),
        ]);

        $this->audit->record('auth.login', $user, newValues: ['guard' => $event->guard], actor: $user);

        // The application session store: the same store the request uses over HTTP,
        // and also available when login happens inside a Livewire update.
        session()->put(EnforceAbsoluteSessionLifetime::SESSION_KEY, CarbonImmutable::now()->getTimestamp());
    }
}
