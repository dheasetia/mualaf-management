<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Services\AccountLockout;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Filament login with per-account lockout and without "remember me"
 * (a remember cookie would bypass the 30-minute idle timeout).
 */
final class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $email = $this->data['email'] ?? null;
        $lockout = app(AccountLockout::class);

        if (is_string($email) && $email !== '' && $lockout->isLocked($email)) {
            throw ValidationException::withMessages([
                'data.email' => __('identity.locked.message', [
                    'minutes' => (int) ceil($lockout->secondsUntilUnlocked($email) / 60),
                ]),
            ]);
        }

        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $this->auditFailedMultiFactorChallenge($exception);

            throw $exception;
        }
    }

    /**
     * A rejected code on the second step: the password was already right, so
     * Laravel's Failed event does not fire. Recorded as auth.mfa_failed.
     */
    private function auditFailedMultiFactorChallenge(ValidationException $exception): void
    {
        $failedChallenge = collect(array_keys($exception->errors()))
            ->contains(static fn (string $key): bool => str_starts_with($key, 'data.multiFactor.'));

        if (! $failedChallenge) {
            return;
        }

        $user = $this->getUserUndertakingMultiFactorAuthentication();

        app(AuditLogger::class)->record(
            'auth.mfa_failed',
            $user instanceof User ? $user : null,
            actorType: ActorType::User,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }
}
