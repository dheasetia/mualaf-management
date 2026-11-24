<?php

declare(strict_types=1);

namespace App\Domain\Registration\Policies;

use App\Domain\Registration\Actions\HistoricalImports;
use App\Domain\Registration\Models\ImportBatch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Imports of the office in context, for those who may import (converts.import
 * with shahadah.record). HistoricalImports checks every step.
 */
final readonly class ImportBatchPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has() && HistoricalImports::mayImport($user);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->viewAny($user) && $batch->tenant_id === $this->tenantContext->id();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportBatch $batch): bool
    {
        return false;
    }

    public function delete(User $user, ImportBatch $batch): bool
    {
        return false;
    }
}
