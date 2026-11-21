<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domain\Tenancy\Exceptions\MissingTenantContext;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * The tenant the current request, job or command acts for.
 *
 * Stored as hidden Laravel Context so it travels with queued jobs automatically
 * and is reset between requests and jobs. Never filled from user input: only from
 * a verified membership (SetTenantContext) or explicit code (run()).
 */
final class TenantContext
{
    private const string KEY = 'tenancy.tenant_id';

    public function set(Tenant $tenant): void
    {
        $this->setId($tenant->id);
    }

    public function setId(int $tenantId): void
    {
        Context::addHidden(self::KEY, $tenantId);
    }

    public function id(): ?int
    {
        $value = Context::getHidden(self::KEY);

        return is_int($value) ? $value : null;
    }

    public function has(): bool
    {
        return $this->id() !== null;
    }

    public function requireId(): int
    {
        return $this->id() ?? throw MissingTenantContext::make();
    }

    public function clear(): void
    {
        Context::forgetHidden(self::KEY);
    }

    /**
     * Runs the callback with the given tenant as context and restores the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant|int $tenant, Closure $callback): mixed
    {
        $previous = $this->id();
        $this->setId($tenant instanceof Tenant ? $tenant->id : $tenant);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->clear() : $this->setId($previous);
        }
    }
}
