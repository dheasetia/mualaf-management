<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Scopes\ScopedThroughConvert;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Written by RecordShahadah and VerifyShahadah. tenant_id is the registering
 * office; rows are read through the convert so later offices see them (03 §3.3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $convert_id
 * @property string $registration_no
 * @property CarbonImmutable $registration_date
 * @property RegistrationStatus $status
 * @property int $registered_by
 * @property int|null $corrected_by
 * @property string|null $rejection_reason note when returned for correction
 * @property string|null $notes
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property-read ShahadahRecord|null $shahadah
 * @property-read User $registeredBy
 * @property-read Convert $convert
 */
final class ConvertRegistration extends Model
{
    use ScopedThroughConvert;

    protected $fillable = [];

    /** @return HasOne<ShahadahRecord, $this> */
    public function shahadah(): HasOne
    {
        return $this->hasOne(ShahadahRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'registration_date' => 'immutable_date',
            'status' => RegistrationStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }
}
