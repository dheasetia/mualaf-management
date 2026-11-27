<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Blocks sign-in and ends access on the next request: canAccessPanel() checks
 * is_active, and a new remember token invalidates "remember me" cookies.
 */
final readonly class DeactivateUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  User|null  $deactivatedBy  null only for system use (console, tests)
     */
    public function handle(User $user, ?User $deactivatedBy = null): User
    {
        if ($deactivatedBy !== null && $user->is($deactivatedBy)) {
            throw IdentityRuleViolation::ownAccount();
        }

        $user->is_active = false;
        $user->setRememberToken(Str::random(60));
        $user->save();

        if ($deactivatedBy !== null) {
            $this->audit->record('users.deactivated', $user, actor: $deactivatedBy);
        }

        return $user;
    }
}
