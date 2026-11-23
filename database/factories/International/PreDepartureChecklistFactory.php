<?php

declare(strict_types=1);

namespace Database\Factories\International;

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<PreDepartureChecklist>
 */
final class PreDepartureChecklistFactory extends Factory
{
    protected $model = PreDepartureChecklist::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'primary_dai_id' => Dai::factory(),
            'status' => ChecklistStatus::Open,
            'created_by' => User::factory(),
        ];
    }
}
