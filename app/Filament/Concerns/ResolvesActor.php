<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * The signed-in user, passed to domain actions as the actor.
 */
trait ResolvesActor
{
    protected function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
