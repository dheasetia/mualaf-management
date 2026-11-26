<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Models\User;

final readonly class ActivateUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  User|null  $activatedBy  null only for system use (console, tests)
     */
    public function handle(User $user, ?User $activatedBy = null): User
    {
        if ($activatedBy !== null && $user->is($activatedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        $user->is_active = true;
        $user->save();

        if ($activatedBy !== null) {
            $this->audit->record('users.activated', $user, actor: $activatedBy);
        }

        return $user;
    }
}
