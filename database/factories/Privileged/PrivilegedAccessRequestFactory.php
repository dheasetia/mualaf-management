<?php

declare(strict_types=1);

namespace Database\Factories\Privileged;

use App\Domain\Privileged\Enums\PrivilegedStatus;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivilegedAccessRequest>
 */
final class PrivilegedAccessRequestFactory extends Factory
{
    protected $model = PrivilegedAccessRequest::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'resource_type' => 'convert',
            'reason' => 'Support ticket',
            'requested_scope' => ['profile'],
            'requested_duration_minutes' => 60,
            'status' => PrivilegedStatus::Pending,
        ];
    }
}
