<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changes another user's name, email or password. A new password signs the user
 * out elsewhere: AuthenticateSession compares the password hash on each request.
 */
final readonly class UpdateUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password?: string|null}  $data
     */
    public function handle(User $user, array $data, User $updatedBy): User
    {
        if ($user->is($updatedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        $email = Str::lower(trim($data['email']));

        if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
            throw IdentityRuleViolation::emailTaken();
        }

        $old = ['name' => $user->name, 'email' => $user->email];
        $user->name = trim($data['name']);
        $user->email = $email;
        $passwordChanged = filled($data['password'] ?? null);

        if ($passwordChanged) {
            $user->password = (string) $data['password'];
            $user->setRememberToken(Str::random(60));
        }

        DB::transaction(function () use ($user, $old, $passwordChanged, $updatedBy): void {
            $user->save();

            $this->audit->record(
                'users.updated',
                $user,
                oldValues: $old,
                newValues: ['name' => $user->name, 'email' => $user->email, 'password_changed' => $passwordChanged],
                actor: $updatedBy,
            );
        });

        return $user;
    }
}
