<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Exceptions\InvalidTenantTransition;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ChangeTenantStatus
{
    public function handle(Tenant $tenant, TenantStatus $target): Tenant
    {
        return DB::transaction(function () use ($tenant, $target): Tenant {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if (! $locked->status->canTransitionTo($target)) {
                throw InvalidTenantTransition::make($locked->status, $target);
            }

            $locked->status = $target;

            if ($target === TenantStatus::Active) {
                $locked->activated_at ??= CarbonImmutable::now();
                $locked->suspended_at = null;
            }

            if ($target === TenantStatus::Suspended) {
                $locked->suspended_at = CarbonImmutable::now();
            }

            $locked->save();

            return $locked;
        });
    }
}
