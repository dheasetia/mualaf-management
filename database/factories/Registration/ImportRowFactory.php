<?php

declare(strict_types=1);

namespace Database\Factories\Registration;

use App\Domain\Registration\Enums\ImportRowStatus;
use App\Domain\Registration\Models\ImportBatch;
use App\Domain\Registration\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<ImportRow>
 */
final class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 100000),
            'source_data_encrypted' => ['full_name' => fake()->name()],
            'status' => ImportRowStatus::Valid,
        ];
    }
}
