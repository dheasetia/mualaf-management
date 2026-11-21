<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets TenantContext for the Center panel after re-checking the user's active membership.
 * The tenant in the URL only selects a context; it is never trusted on its own.
 */
final readonly class SetTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();
        $user = $request->user();

        if (! $tenant instanceof Tenant || ! $user instanceof User || ! $user->canAccessTenant($tenant)) {
            abort(404);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
