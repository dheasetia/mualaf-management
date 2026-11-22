<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Enrollments belong to the office that runs them (service_tenant_id).
 * Fails closed without tenant context.
 *
 * @implements Scope<Model>
 */
final class EnrollmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('service_tenant_id'), app(TenantContext::class)->requireId());
    }
}
