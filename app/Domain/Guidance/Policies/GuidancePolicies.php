<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Policies;

use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Programs, classes and enrollments. guidance.view reads; teachers (see
 * TeacherAssignments::onlyOwnClasses) only their own classes. Nothing here is
 * deleted: classes are cancelled, enrollments withdrawn.
 */
final readonly class GuidancePolicies
{
    public function __construct(private TenantContext $tenantContext, private TeacherAssignments $teachers) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('guidance.view');
    }

    public function view(User $user, Model $model): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return match (true) {
            $model instanceof GuidanceClass => ! $this->teachers->onlyOwnClasses($user) || $this->teachers->teaches($user, $model->id),
            $model instanceof Enrollment => ! $this->teachers->onlyOwnClasses($user) || ($model->class_id !== null && $this->teachers->teaches($user, $model->class_id)),
            default => true,
        };
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() && ($user->hasPermission('guidance.programs.manage') || $user->hasPermission('guidance.classes.manage'));
    }

    public function update(User $user, Model $model): bool
    {
        return match (true) {
            $model instanceof GuidanceProgram => $user->hasPermission('guidance.programs.manage') && $model->tenant_id === $this->tenantContext->id(),
            $model instanceof GuidanceClass => $user->hasPermission('guidance.classes.manage'),
            default => false,
        };
    }

    public function enroll(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('guidance.enroll');
    }

    public function teach(User $user, GuidanceClass $class): bool
    {
        return $this->teachers->teaches($user, $class->id);
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
