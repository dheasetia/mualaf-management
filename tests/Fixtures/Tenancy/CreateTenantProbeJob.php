<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tenancy;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CreateTenantProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $name) {}

    public function handle(): void
    {
        TenantProbe::query()->create(['name' => $this->name]);
    }
}
