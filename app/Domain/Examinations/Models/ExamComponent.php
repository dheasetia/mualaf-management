<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $exam_id
 * @property string $component_code
 * @property string $name
 * @property string $max_score
 * @property string $weight
 */
final class ExamComponent extends Model
{
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }
}
