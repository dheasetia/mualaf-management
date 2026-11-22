<?php

declare(strict_types=1);

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Models\User;

/**
 * Letter templates are managed from Central (documents.templates.manage).
 */
final class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.templates.manage');
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return ! $template->isApproved() && $this->viewAny($user);
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
