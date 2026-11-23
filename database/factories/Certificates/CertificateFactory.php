<?php

declare(strict_types=1);

namespace Database\Factories\Certificates;

use App\Domain\Certificates\Enums\CertificateStatus;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Converts\Models\Convert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only (a pending request). Use inside inTenant().
 *
 * @extends Factory<Certificate>
 */
final class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'convert_id' => Convert::factory(),
            'certificate_type' => CertificateType::Shahadah,
            'status' => CertificateStatus::PendingApproval,
            'requested_by' => User::factory(),
        ];
    }
}
