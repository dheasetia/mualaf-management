<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Services;

use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;

/**
 * Pilgrimage programs for other modules (dawah activities, transport), in
 * the office in context.
 */
final class ProgramDirectory
{
    /**
     * Programs an activity can still be linked to.
     *
     * @return array<int, string>
     */
    public function linkableOptions(): array
    {
        return PilgrimageProgram::query()
            ->whereNotIn('status', [ProgramStatus::Completed, ProgramStatus::Cancelled])
            ->orderByDesc('starts_on')
            ->get()
            ->mapWithKeys(fn (PilgrimageProgram $program): array => [$program->id => "{$program->name} ({$program->season_year})"])
            ->all();
    }

    public function name(?int $programId): ?string
    {
        return $programId === null ? null : PilgrimageProgram::query()->whereKey($programId)->value('name');
    }

    /**
     * Converts travelling with the program: approved or confirmed.
     *
     * @return list<int>
     */
    public function travellingConvertIds(int $programId): array
    {
        return array_values(PilgrimageRegistration::query()
            ->where('program_id', $programId)
            ->whereIn('status', [RegistrationStatus::Approved, RegistrationStatus::Confirmed])
            ->orderBy('id')
            ->pluck('convert_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
