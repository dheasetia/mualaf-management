<?php

declare(strict_types=1);

namespace App\Domain\Dai\Contracts;

/**
 * A person da'i are assigned to and follow up (prospect, convert). Lets the
 * Dai module work with them without importing their models.
 *
 * Implemented by Eloquent models only.
 */
interface AssignableSubject
{
    /** The office responsible for the person: prospect tenant_id, convert current_tenant_id. */
    public function responsibleTenantId(): int;

    /** Copy of the active primary assignment, written by DaiAssignmentService only. */
    public function setPrimaryDai(int $daiId): void;
}
