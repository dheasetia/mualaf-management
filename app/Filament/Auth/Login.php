<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Identity\Services\AccountLockout;
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

        return parent::authenticate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }
}
