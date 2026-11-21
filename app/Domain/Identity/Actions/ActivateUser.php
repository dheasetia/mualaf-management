<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;

final class ActivateUser
{
    public function handle(User $user): User
    {
        $user->is_active = true;
        $user->save();

        return $user;
    }
}
