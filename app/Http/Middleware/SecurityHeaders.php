<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * 13 §13.5 headers on every web response. The CSP allows only this origin
 * (plus the Vite dev server locally and Filament's font host). Scripts run
 * from this origin or carry the per-request nonce, which Livewire and
 * Filament add to their script tags (Vite::cspNonce()); an injected
 * <script> or inline handler is blocked. 'unsafe-eval' stays because
 * Alpine evaluates Filament's directives with Function(): the CSP-safe
 * Alpine build cannot parse the statements Filament 5 uses (if, let,
 * arrow functions). No framing, plugins, foreign forms or base URLs.
 * HSTS only over HTTPS.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('Content-Security-Policy', $this->policy($nonce));
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $dev = app()->isLocal() ? ' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173' : '';
        $fonts = (string) config('security.font_host');

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'".$dev,
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' ".$fonts.$dev,
            "font-src 'self' data: ".$fonts,
            "img-src 'self' data: blob:",
            "connect-src 'self'".$dev,
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
