<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\International\Enums\ChecklistStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\International\PreDepartureChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Checklist before a convert returns home (PRD §33).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property int $primary_dai_id
 * @property CarbonImmutable|null $expected_departure_on
 * @property int|null $destination_country_id
 * @property ChecklistStatus $status
 * @property CarbonImmutable|null $completed_at
 * @property int|null $referral_id
 * @property int $created_by
 */
#[UseFactory(PreDepartureChecklistFactory::class)]
final class PreDepartureChecklist extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PreDepartureChecklistFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return HasMany<PreDepartureChecklistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PreDepartureChecklistItem::class, 'checklist_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_departure_on' => 'immutable_date',
            'status' => ChecklistStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }
}
