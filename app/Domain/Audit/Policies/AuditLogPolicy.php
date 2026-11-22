<?php

declare(strict_types=1);

namespace App\Domain\Audit\Policies;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Read-only: nobody creates, edits or deletes audit entries through the UI.
 *
 * Scope follows Authorizer: without tenant context only platform roles count
 * (audit.view_national, every entry); with tenant context only office roles
 * count (audit.view_office, entries of that tenant only).
 */
final readonly class AuditLogPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            ? $user->hasPermission('audit.view_office')
            : $user->hasPermission('audit.view_national');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return ! $this->tenantContext->has() || $auditLog->tenant_id === $this->tenantContext->id();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
