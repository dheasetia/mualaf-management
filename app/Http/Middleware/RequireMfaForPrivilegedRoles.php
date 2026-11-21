<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Rbac\Authorizer;
use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Center panel: users holding a role that requires MFA (approve, verify, reveal, ...)
 * must enable an authenticator before using the panel. Filament's own "MFA required"
 * switch is panel-wide and fixed at boot, so per-role enforcement lives here.
 */
final readonly class RequireMfaForPrivilegedRoles
{
    public function __construct(private Authorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $request->routeIs('filament.*.auth.profile')
            || ! $this->authorizer->holdsRoleRequiringMfa($user)
            || MultiFactorChallenge::make()->hasEnabledProviders($user)
        ) {
            return $next($request);
        }

        Notification::make()
            ->title(__('identity.mfa_required.title'))
            ->body(__('identity.mfa_required.body'))
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to(Filament::getProfileUrl() ?? '/');
    }
}
