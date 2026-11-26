<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Exceptions\RoleAlreadyAssigned;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bootstrap only: gives the first platform administrator the Super Admin role.
 * Later assignments go through the Central panel by someone holding users.assign_roles.
 */
#[Signature('rbac:grant-super-admin {email : Email of an existing user}')]
#[Description('Assign the Super Admin platform role to an existing user (bootstrap)')]
final class GrantSuperAdmin extends Command
{
    public function handle(AssignRole $assignRole): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        try {
            $assignRole->handle($user, Role::query()->where('code', 'super_admin')->sole(), null, null);
        } catch (RoleAlreadyAssigned) {
            $this->info('The user is already Super Admin.');

            return self::SUCCESS;
        }

        $this->info('Super Admin assigned. The user can now sign in to /central.');

        return self::SUCCESS;
    }
}
