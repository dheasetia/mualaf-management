<?php

declare(strict_types=1);

namespace App\Domain\Transport\Models;

use App\Domain\Transport\Enums\TripStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Transport\TransportTripFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One vehicle run with one driver; pickups are grouped on it (08 §8.7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $vehicle_id
 * @property int $driver_id
 * @property int|null $activity_transport_request_id
 * @property CarbonImmutable $departs_at
 * @property CarbonImmutable $returns_at
 * @property TripStatus $status
 * @property string|null $cancellation_reason
 * @property int $created_by
 * @property-read Vehicle $vehicle
 * @property-read Driver $driver
 */
#[UseFactory(TransportTripFactory::class)]
final class TransportTrip extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TransportTripFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return HasMany<TransportRequest, $this> */
    public function pickups(): HasMany
    {
        return $this->hasMany(TransportRequest::class, 'trip_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'departs_at' => 'immutable_datetime',
            'returns_at' => 'immutable_datetime',
            'status' => TripStatus::class,
        ];
    }
}
