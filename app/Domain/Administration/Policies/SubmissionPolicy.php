<?php

declare(strict_types=1);

namespace App\Domain\Administration\Policies;

use App\Domain\Administration\Models\Submission;
use App\Models\User;

/**
 * Submissions and their attachments (including medical letters) are read
 * with submissions.view only; steps are checked by SubmissionWorkflow.
 */
final class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('submissions.view');
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('submissions.create');
    }

    public function update(User $user, Submission $submission): bool
    {
        return false;
    }

    public function delete(User $user, Submission $submission): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
