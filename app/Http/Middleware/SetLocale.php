<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language: the signed-in user's users.locale, otherwise the
 * language chosen in this session (login page), otherwise APP_LOCALE.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user instanceof User ? $user->locale : null;

        if (! Locales::isSupported($locale) && $request->hasSession()) {
            $locale = $request->session()->get('locale');
        }

        if (Locales::isSupported($locale)) {
            app()->setLocale((string) $locale);
        }

        return $next($request);
    }
}
