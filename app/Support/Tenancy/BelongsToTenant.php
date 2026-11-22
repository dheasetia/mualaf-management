<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantMismatch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models owned by one tenant through a `tenant_id` column
 * (03-tenancy-authorization §3.2, "Tenant-owned").
 *
 * - every query is scoped to the current tenant;
 * - tenant_id is filled from TenantContext on create, and a different value is rejected;
 * - tenant_id cannot change on update, unless the model allows a sanctioned
 *   move through tenantMoveAllowed() (prospect transfer).
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $contextId = app(TenantContext::class)->requireId();
            $given = $model->getAttribute('tenant_id');

            if ($given !== null && (int) $given !== $contextId) {
                throw TenantMismatch::onCreate($model::class, (int) $given, $contextId);
            }

            $model->setAttribute('tenant_id', $contextId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id') && ! (method_exists($model, 'tenantMoveAllowed') && $model->tenantMoveAllowed())) {
                throw TenantMismatch::onUpdate($model::class);
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
