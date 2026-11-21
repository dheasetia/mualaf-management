<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a signed-in session after a fixed time regardless of activity
 * (idle timeout is SESSION_LIFETIME; this is the absolute cap, default 12 hours).
 */
final class EnforceAbsoluteSessionLifetime
{
    public const string SESSION_KEY = 'auth.started_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $startedAt = $session->get(self::SESSION_KEY);

        if (! is_int($startedAt)) {
            $session->put(self::SESSION_KEY, CarbonImmutable::now()->getTimestamp());

            return $next($request);
        }

        $maxMinutes = (int) config('session.absolute_lifetime', 720);

        if (CarbonImmutable::now()->getTimestamp() - $startedAt > $maxMinutes * 60) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->guest(Filament::getLoginUrl() ?? '/');
        }

        return $next($request);
    }
}
