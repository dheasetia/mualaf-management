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
        $dev = app()->isLocal() ? $this->devServerSources() : '';
        $fonts = (string) config('security.font_host');

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'".$dev,
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' ".$fonts.$dev,
            "font-src 'self' data: ".$fonts.$dev,
            "img-src 'self' data: blob:".$dev,
            "connect-src 'self'".$dev,
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }

    /**
     * Local only: the Vite dev server. Besides localhost, the address in Vite's hot
     * file (Herd serves it at https://<site>.test:5173), with its WebSocket form.
     */
    private function devServerSources(): string
    {
        $sources = ['http://localhost:5173', 'ws://localhost:5173', 'http://127.0.0.1:5173', 'ws://127.0.0.1:5173'];
        $hotFile = Vite::hotFile();
        $url = is_file($hotFile) ? trim((string) file_get_contents($hotFile)) : '';
        $parts = parse_url($url);

        if (is_array($parts) && isset($parts['scheme'], $parts['host']) && in_array($parts['scheme'], ['http', 'https'], true)) {
            $origin = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $sources[] = $parts['scheme'].'://'.$origin;
            $sources[] = ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$origin;
        }

        return ' '.implode(' ', array_unique($sources));
    }
}
