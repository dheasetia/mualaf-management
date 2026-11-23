<?php

declare(strict_types=1);

namespace Database\Factories\Reporting;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Reporting\Models\ExportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportJob>
 */
final class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'export_type' => ExportType::ShahadahStatistics,
            'filters' => [],
            'status' => ExportStatus::Queued,
        ];
    }
}
