<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id (shared by its audit entries and logs) and keeps
 * the client address for audit. Context travels with queued jobs, so work a
 * request dispatches is audited with the request's origin.
 *
 * The id is always generated here; a client-supplied X-Request-Id is ignored.
 */
final class AssignRequestId
{
    public const string REQUEST_ID_KEY = 'request_id';

    public const string IP_KEY = 'audit.ip_address';

    public const string USER_AGENT_KEY = 'audit.user_agent';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Context::add(self::REQUEST_ID_KEY, $requestId);
        Context::addHidden(self::IP_KEY, $request->ip());
        Context::addHidden(self::USER_AGENT_KEY, Str::limit((string) $request->userAgent(), 255, '') ?: null);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
