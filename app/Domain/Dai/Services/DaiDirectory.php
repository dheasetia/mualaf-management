<?php

declare(strict_types=1);

namespace App\Domain\Dai\Services;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Models\Dai;
use App\Models\User;

/**
 * Read access to da'i of the office in context for other modules, by id.
 */
final class DaiDirectory
{
    /** The da'i record linked to the user in this office, if any. */
    public function daiIdForUser(User $user): ?int
    {
        $id = Dai::query()->where('user_id', $user->id)->value('id');

        return is_int($id) ? $id : null;
    }

    /**
     * Active da'i of this office for pickers: id => name.
     *
     * @return array<int, string>
     */
    public function activeOptions(): array
    {
        return Dai::query()->where('status', DaiStatus::Active)->orderBy('full_name')->pluck('full_name', 'id')->all();
    }

    /** An active da'i of this office by employee number (imports). */
    public function activeIdByEmployeeNumber(string $employeeNumber): ?int
    {
        $id = Dai::query()->where('employee_number', $employeeNumber)->where('status', DaiStatus::Active)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Names of da'i by id (any status), for lists.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(array $ids): array
    {
        return $ids === [] ? [] : Dai::query()->whereKey($ids)->pluck('full_name', 'id')->all();
    }
}
