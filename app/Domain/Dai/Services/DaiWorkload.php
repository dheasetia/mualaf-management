<?php

declare(strict_types=1);

namespace App\Domain\Dai\Services;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Models\Dai;
use Illuminate\Database\Eloquent\Builder;

/**
 * Current load per da'i of the office in context: active primary assignments
 * by subject type, other active assignments, and active supervisees.
 */
final class DaiWorkload
{
    /** @return Builder<Dai> */
    public function query(): Builder
    {
        return Dai::query()
            ->whereIn('status', [DaiStatus::Active, DaiStatus::OnLeave])
            ->withCount([
                'assignments as primary_prospects_count' => fn (Builder $query) => $query
                    ->whereNull('ended_at')->where('is_primary', true)->where('subject_type', 'prospect'),
                'assignments as primary_converts_count' => fn (Builder $query) => $query
                    ->whereNull('ended_at')->where('is_primary', true)->where('subject_type', 'convert'),
                'assignments as other_assignments_count' => fn (Builder $query) => $query
                    ->whereNull('ended_at')->where('is_primary', false),
                'supervisees as supervisees_count' => fn (Builder $query) => $query
                    ->where('status', DaiStatus::Active),
            ]);
    }
}
