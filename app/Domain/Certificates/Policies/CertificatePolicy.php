<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Policies;

use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Certificates: certificates.view reads; request, approve, issue and revoke
 * each need their permission (the actions enforce the state rules).
 * Templates: documents.templates.manage, national ones from Central.
 */
final readonly class CertificatePolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has()
            ? collect(['certificates.view', 'certificates.request', 'certificates.approve', 'certificates.issue', 'certificates.revoke'])->contains(fn (string $permission): bool => $user->hasPermission($permission))
            : $user->hasPermission('documents.templates.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() ? $user->hasPermission('certificates.request') : $user->hasPermission('documents.templates.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof CertificateTemplate && ! $model->isApproved() && $user->hasPermission('documents.templates.manage');
    }

    public function approve(User $user, Certificate $certificate): bool
    {
        return $user->hasPermission('certificates.approve') && $certificate->status === CertificateStatus::PendingApproval && $certificate->requested_by !== $user->id;
    }

    public function issue(User $user, Certificate $certificate): bool
    {
        return $user->hasPermission('certificates.issue') && $certificate->status === CertificateStatus::Approved;
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->hasPermission('certificates.revoke') && $certificate->status === CertificateStatus::Issued;
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return $this->viewAny($user) && $certificate->hasPdf() && in_array($certificate->status, [CertificateStatus::Issued, CertificateStatus::Revoked], true);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
