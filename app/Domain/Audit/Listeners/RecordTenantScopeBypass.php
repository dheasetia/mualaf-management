<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Support\Tenancy\Events\TenantScopeBypassed;

final readonly class RecordTenantScopeBypass
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(TenantScopeBypassed $event): void
    {
        $this->audit->recordLater('tenancy.bypassed', newValues: ['reason' => $event->reason]);
    }
}
