<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\International\Enums\ChecklistItemCode;
use App\Domain\International\Enums\ItemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $checklist_id
 * @property ChecklistItemCode $item_code
 * @property ItemStatus $status
 * @property int|null $done_by
 * @property CarbonImmutable|null $done_at
 * @property string|null $notes
 */
final class PreDepartureChecklistItem extends Model
{
    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope('through_checklist', fn (Builder $builder) => $builder->whereHas('checklist'));
    }

    /** @return BelongsTo<PreDepartureChecklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(PreDepartureChecklist::class, 'checklist_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_code' => ChecklistItemCode::class,
            'status' => ItemStatus::class,
            'done_at' => 'immutable_datetime',
        ];
    }
}
