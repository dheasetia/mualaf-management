<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A transfer request is visible to both offices involved, and only to them.
 * Fails closed without tenant context.
 *
 * @implements Scope<Model>
 */
final class TransferPartyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->requireId();

        $builder->where(fn (Builder $query) => $query
            ->where($model->qualifyColumn('from_tenant_id'), $tenantId)
            ->orWhere($model->qualifyColumn('to_tenant_id'), $tenantId));
    }
}
