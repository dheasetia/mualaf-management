<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Models;

use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Dawah\DawahMaterialDistributionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $activity_plan_id
 * @property int $dai_id
 * @property int $material_id
 * @property int|null $prospect_id
 * @property int|null $convert_id
 * @property int $quantity
 * @property CarbonImmutable $distributed_at
 * @property string|null $notes
 * @property-read DawahMaterial $material
 */
#[UseFactory(DawahMaterialDistributionFactory::class)]
final class DawahMaterialDistribution extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DawahMaterialDistributionFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<DawahMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(DawahMaterial::class, 'material_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'distributed_at' => 'immutable_datetime'];
    }
}
