<?php

declare(strict_types=1);

namespace Database\Factories\Administration;

use App\Domain\Administration\Models\SubmissionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionType>
 */
final class SubmissionTypeFactory extends Factory
{
    protected $model = SubmissionType::class;

    public function definition(): array
    {
        return [
            'code' => 'type_'.fake()->unique()->numerify('####'),
            'name_ar' => 'طلب',
            'required_documents' => [['code' => 'iqamah_copy', 'name_ar' => 'صورة الإقامة', 'name_en' => 'Iqamah copy', 'required' => true]],
            'workflow_config' => ['approval_stages' => 1, 'sla_days' => 5],
            'active' => true,
        ];
    }
}
