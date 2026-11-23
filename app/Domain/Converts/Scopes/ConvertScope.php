<?php

declare(strict_types=1);

namespace App\Domain\Converts\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Converts belong to the office currently responsible for them
 * (03-tenancy-authorization §3.2). Fails closed without tenant context, unless
 * a domain service bypasses it through TenantContext::bypass().
 *
 * @implements Scope<Model>
 */
final class ConvertScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $builder->where($model->qualifyColumn('current_tenant_id'), $context->requireId());
    }
}
