<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\UserRoleAssignment;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use Carbon\CarbonImmutable;

/**
 * Periodic access review (13 §13.6, "laporan akses berkala"): each account
 * with active roles in an office (or platform roles when $tenantId is null),
 * flagging privileged roles without an authenticator and unused accounts.
 */
final class AccessReview
{
    /**
     * @return list<array{user_id: int, name: string, email: string, roles: list<string>, mfa: bool, last_login_at: CarbonImmutable|null, missing_mfa: bool, stale: bool}>
     */
    public function rows(?int $tenantId): array
    {
        $staleBefore = CarbonImmutable::now()->subDays((int) config('security.stale_account_days'));
        $assignments = UserRoleAssignment::query()->active()->with(['role', 'user'])
            ->when($tenantId === null, fn ($query) => $query->whereNull('tenant_id'), fn ($query) => $query->where('tenant_id', $tenantId))
            ->get()->groupBy('user_id');
        $rows = [];

        foreach ($assignments as $userAssignments) {
            $user = $userAssignments->first()?->user;

            if (! $user instanceof User) {
                continue;
            }

            $lastLogin = $user->getAttribute('last_login_at');
            $lastLogin = $lastLogin === null ? null : CarbonImmutable::parse((string) $lastLogin);
            $mfa = $user->getAppAuthenticationSecret() !== null;
            $rows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => array_values($userAssignments->map(fn (UserRoleAssignment $assignment): string => (string) LocalizedName::of($assignment->role))->all()),
                'mfa' => $mfa,
                'last_login_at' => $lastLogin,
                'missing_mfa' => ! $mfa && $userAssignments->contains(fn (UserRoleAssignment $assignment): bool => $assignment->role->requires_mfa),
                'stale' => $lastLogin === null ? $user->created_at?->lessThan($staleBefore) ?? false : $lastLogin->lessThan($staleBefore),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [(int) ! ($a['missing_mfa'] || $a['stale']), $a['name']] <=> [(int) ! ($b['missing_mfa'] || $b['stale']), $b['name']]);

        return $rows;
    }
}
