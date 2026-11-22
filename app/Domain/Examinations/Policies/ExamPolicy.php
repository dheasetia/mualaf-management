<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Policies;

use App\Domain\Examinations\Models\Exam;
use App\Domain\Guidance\Services\TeacherAssignments;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * exams.view reads every exam of the office; graders without exams.view or
 * exams.manage (teachers) see the exams of the classes they teach.
 */
final readonly class ExamPolicy
{
    public function __construct(private TenantContext $tenantContext, private TeacherAssignments $teachers) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->has() && ($user->hasPermission('exams.view') || $user->hasPermission('exams.grade'));
    }

    public function view(User $user, Exam $exam): bool
    {
        return $this->viewAny($user) && (! $this->onlyOwnClasses($user) || ($exam->class_id !== null && $this->teachers->teaches($user, $exam->class_id)));
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->has() && $user->hasPermission('exams.manage');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Exam $exam): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function onlyOwnClasses(User $user): bool
    {
        return ! $user->hasPermission('exams.view') && ! $user->hasPermission('exams.manage');
    }
}
