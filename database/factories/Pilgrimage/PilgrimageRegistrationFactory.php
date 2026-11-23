<?php

declare(strict_types=1);

namespace Database\Factories\Pilgrimage;

use App\Domain\Converts\Models\Convert;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<PilgrimageRegistration>
 */
final class PilgrimageRegistrationFactory extends Factory
{
    protected $model = PilgrimageRegistration::class;

    public function definition(): array
    {
        return [
            'program_id' => PilgrimageProgram::factory(),
            'convert_id' => Convert::factory(),
            'status' => RegistrationStatus::Applied,
            'registered_by' => User::factory(),
        ];
    }
}
