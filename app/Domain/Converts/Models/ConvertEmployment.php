<?php

declare(strict_types=1);

namespace App\Domain\Converts\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Converts\Enums\JobStatus;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\Platform\Models\City;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by SaveConvertEmployment. At most one current employment per convert.
 *
 * @property int $id
 * @property int $convert_id
 * @property int|null $company_id
 * @property string|null $company_name_free
 * @property string|null $occupation
 * @property JobStatus $employment_status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property bool $is_current
 * @property string|null $work_latitude
 * @property string|null $work_longitude
 * @property string|null $work_google_maps_url
 * @property-read Company|null $company
 */
final class ConvertEmployment extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    protected $hidden = ['work_phone_encrypted'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<City, $this> */
    public function workCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'work_city_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_phone_encrypted' => 'encrypted',
            'employment_status' => JobStatus::class,
            'started_at' => 'immutable_date',
            'ended_at' => 'immutable_date',
            'is_current' => 'boolean',
            'work_latitude' => 'decimal:7',
            'work_longitude' => 'decimal:7',
        ];
    }
}
