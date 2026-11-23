<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\Registration\Enums\ShahadahRecordType;
use App\Domain\Registration\Enums\ShahadahStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Sensitive (04 §4.5): shown only with converts.view_sensitive.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $convert_id
 * @property int $convert_registration_id
 * @property ShahadahRecordType $record_type
 * @property CarbonImmutable $shahadah_date
 * @property string|null $shahadah_time
 * @property string $location
 * @property int|null $city_id
 * @property int|null $preacher_dai_id
 * @property int $recorded_by
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property ShahadahStatus $status
 * @property string|null $notes
 */
final class ShahadahRecord extends Model
{
    use HasUuids;
    use ScopedThroughConvert;

    protected $fillable = [];

    /** Generated column: never written by the application. */
    protected $guarded = ['active_first_key'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'record_type' => ShahadahRecordType::class,
            'shahadah_date' => 'immutable_date',
            'status' => ShahadahStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
