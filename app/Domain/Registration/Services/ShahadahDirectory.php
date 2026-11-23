<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Enums\ShahadahRecordType;
use App\Domain\Registration\Enums\ShahadahStatus;
use App\Domain\Registration\Models\ShahadahRecord;
use Carbon\CarbonImmutable;

/**
 * Shahadah facts for other modules (certificates), by convert id.
 */
final class ShahadahDirectory
{
    /** @return array{date: CarbonImmutable, location: string, verified_at: CarbonImmutable}|null */
    public function verifiedFirst(int $convertId): ?array
    {
        $record = ShahadahRecord::query()
            ->where('convert_id', $convertId)
            ->where('record_type', ShahadahRecordType::First)
            ->where('status', ShahadahStatus::Verified)
            ->first();

        if ($record === null || $record->verified_at === null) {
            return null;
        }

        return ['date' => $record->shahadah_date, 'location' => $record->location, 'verified_at' => $record->verified_at];
    }
}
