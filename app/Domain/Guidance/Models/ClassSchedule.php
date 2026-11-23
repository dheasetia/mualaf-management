<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Weekly pattern of a class (day_of_week 0 = Sunday).
 *
 * @property int $id
 * @property int $class_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 */
final class ClassSchedule extends Model
{
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
