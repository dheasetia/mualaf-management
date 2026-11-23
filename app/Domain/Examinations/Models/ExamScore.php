<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $exam_attempt_id
 * @property int $exam_component_id
 * @property string $score
 * @property string|null $notes
 */
final class ExamScore extends Model
{
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }
}
