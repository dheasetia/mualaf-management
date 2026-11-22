<?php

declare(strict_types=1);

namespace Database\Factories\Documents;

use App\Domain\Documents\Enums\GeneratedStatus;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Use inside inTenant().
 *
 * @extends Factory<GeneratedDocument>
 */
final class GeneratedDocumentFactory extends Factory
{
    protected $model = GeneratedDocument::class;

    public function definition(): array
    {
        return [
            'template_id' => fn (): int => DocumentTemplate::query()->create([
                'template_code' => 'T'.fake()->unique()->numerify('####'),
                'document_type' => LetterType::OtherLetter,
                'name_ar' => 'قالب',
                'html_template' => '<p>{{ full_name }}</p>',
                'version' => 1,
            ])->id,
            'subject_type' => 'convert',
            'subject_id' => fake()->numberBetween(1, 100000),
            'document_number' => 'DOC-'.fake()->unique()->numerify('########'),
            'snapshot_data' => [],
            'status' => GeneratedStatus::Issued,
            'generated_by' => User::factory(),
        ];
    }
}
