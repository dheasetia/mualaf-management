<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an account with a password the administrator chose; the administrator
 * passes it on. Roles and office membership are given separately.
 */
final readonly class CreateUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data, User $createdBy): User
    {
        $email = Str::lower(trim($data['email']));

        if (User::query()->where('email', $email)->exists()) {
            throw IdentityRuleViolation::emailTaken();
        }

        return DB::transaction(function () use ($data, $email, $createdBy): User {
            $user = new User;
            $user->name = trim($data['name']);
            $user->email = $email;
            $user->password = $data['password'];
            $user->save();

            $this->audit->record('users.created', $user, newValues: ['name' => $user->name, 'email' => $user->email], actor: $createdBy);

            return $user;
        });
    }
}
