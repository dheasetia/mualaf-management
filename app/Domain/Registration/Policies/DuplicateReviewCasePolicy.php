<?php

declare(strict_types=1);

namespace App\Domain\Registration\Policies;

use App\Domain\Registration\Enums\ReviewStatus;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

final readonly class DuplicateReviewCasePolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('duplicates.review');
    }

    public function view(User $user, DuplicateReviewCase $case): bool
    {
        return $this->viewAny($user) && $case->tenant_id === $this->tenantContext->id();
    }

    public function resolve(User $user, DuplicateReviewCase $case): bool
    {
        return $this->view($user, $case) && $case->status !== ReviewStatus::Resolved;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DuplicateReviewCase $case): bool
    {
        return false;
    }

    public function delete(User $user, DuplicateReviewCase $case): bool
    {
        return false;
    }
}
