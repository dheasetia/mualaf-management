<?php

declare(strict_types=1);

namespace Database\Factories\Guidance;

use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\GenderPolicy;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Platform\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use inside inTenant(). Open, mixed gender, capacity 20.
 *
 * @extends Factory<GuidanceClass>
 */
final class GuidanceClassFactory extends Factory
{
    protected $model = GuidanceClass::class;

    public function definition(): array
    {
        return [
            'program_id' => GuidanceProgram::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CLS-##??')),
            'name' => 'Class '.fake()->unique()->numberBetween(1, 9999),
            'language_id' => Language::factory(),
            'gender_policy' => GenderPolicy::Mixed,
            'capacity' => 20,
            'starts_on' => now()->subWeek()->toDateString(),
            'ends_on' => now()->addMonths(3)->toDateString(),
            'status' => ClassStatus::Open,
        ];
    }
}
