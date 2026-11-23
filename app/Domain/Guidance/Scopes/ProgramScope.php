<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Programs are national (templates, tenant_id NULL) or an office's own: an
 * office sees both, Central only national ones.
 *
 * @implements Scope<Model>
 */
final class ProgramScope implements Scope
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
