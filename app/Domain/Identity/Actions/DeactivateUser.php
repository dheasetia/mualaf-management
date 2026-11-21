<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Blocks sign-in and ends access on the next request: canAccessPanel() checks
 * is_active, and a new remember token invalidates "remember me" cookies.
 */
final class DeactivateUser
{
    public function handle(User $user): User
    {
        $user->is_active = false;
        $user->setRememberToken(Str::random(60));
        $user->save();

        return $user;
    }
}
