<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $module_id
 * @property string $title
 * @property int $sort_order
 * @property-read GuidanceModule $module
 */
final class GuidanceLesson extends Model
{
    protected $fillable = [];

    /** @return BelongsTo<GuidanceModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(GuidanceModule::class, 'module_id');
    }
}
