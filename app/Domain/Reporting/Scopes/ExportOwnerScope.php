<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Exports belong to an office (in its context) or to Central (no context).
 *
 * @implements Scope<Model>
 */
final class ExportOwnerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();
        $column = $model->qualifyColumn('tenant_id');

        $tenantId === null ? $builder->whereNull($column) : $builder->where($column, $tenantId);
    }
}
