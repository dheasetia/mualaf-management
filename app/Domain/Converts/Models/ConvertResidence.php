<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Converts\Enums\ResidenceType;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\Platform\Models\City;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by SaveConvertResidence. At most one current residence per convert.
 *
 * @property int $id
 * @property int $convert_id
 * @property ResidenceType $residence_type
 * @property string|null $name
 * @property int|null $city_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $google_maps_url
 * @property bool $is_pickup_point
 * @property bool $is_current
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 */
final class ConvertResidence extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'residence_type' => ResidenceType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_pickup_point' => 'boolean',
            'started_at' => 'immutable_date',
            'ended_at' => 'immutable_date',
            'is_current' => 'boolean',
        ];
    }
}
