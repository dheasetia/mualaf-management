<?php

declare(strict_types=1);

namespace Database\Factories\Registration;

use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Domain\Registration\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only; batches are created by HistoricalImports. Use inside inTenant().
 *
 * @extends Factory<ImportBatch>
 */
final class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'import_type' => ImportBatch::TYPE_HISTORICAL_CONVERTS,
            'original_filename' => 'converts.csv',
            'status' => ImportBatchStatus::Validated,
            'uploaded_by' => User::factory(),
        ];
    }
}
