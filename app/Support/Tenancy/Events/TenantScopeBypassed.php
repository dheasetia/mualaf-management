<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A domain service read across tenants through TenantContext::bypass().
 * The audit module records it.
 */
final readonly class TenantScopeBypassed
{
    use Dispatchable;

    public function __construct(public string $reason) {}
}
