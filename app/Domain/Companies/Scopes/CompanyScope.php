<?php

declare(strict_types=1);

namespace App\Domain\Companies\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * "Global or tenant" (03-tenancy-authorization §3.2): an office sees the
 * national directory and its own companies; without tenant context (Central)
 * only the national directory.
 *
 * @implements Scope<Model>
 */
final class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();
        $column = $model->qualifyColumn('tenant_id');

        $builder->where(fn (Builder $query) => $tenantId === null
            ? $query->whereNull($column)
            : $query->whereNull($column)->orWhere($column, $tenantId));
    }
}
