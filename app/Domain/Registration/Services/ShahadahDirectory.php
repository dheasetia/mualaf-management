<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Enums\ShahadahRecordType;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Models\ShahadahRecord;
use App\Domain\Registration\Models\ShahadahWitness;
use Carbon\CarbonImmutable;

/**
 * Shahadah facts for other modules (certificates), by convert id.
 */
final class ShahadahDirectory
{
    /** @return array{date: CarbonImmutable, location: string, verified_at: CarbonImmutable, witnesses: list<string>}|null */
    public function verifiedFirst(int $convertId): ?array
    {
        $record = ShahadahRecord::query()
            ->where('convert_id', $convertId)
            ->where('record_type', ShahadahRecordType::First)
            ->where('status', ShahadahStatus::Verified)
            ->with('witnesses')
            ->first();

        if ($record === null || $record->verified_at === null) {
            return null;
        }

        return [
            'date' => $record->shahadah_date,
            'location' => $record->location,
            'verified_at' => $record->verified_at,
            'witnesses' => array_values($record->witnesses->map(fn (ShahadahWitness $witness): string => $witness->name)->all()),
        ];
    }
}
