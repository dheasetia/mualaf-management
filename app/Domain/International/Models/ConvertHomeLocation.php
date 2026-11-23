<?php

declare(strict_types=1);

namespace App\Domain\International\Models;

use App\Domain\Converts\Scopes\ScopedThroughConvert;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $convert_id
 * @property int $country_id
 * @property string|null $province_state
 * @property string|null $city
 * @property string|null $address
 * @property bool $is_primary
 * @property int $created_by
 */
final class ConvertHomeLocation extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
