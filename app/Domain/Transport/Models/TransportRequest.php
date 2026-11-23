<?php

declare(strict_types=1);

namespace App\Domain\Transport\Models;

use App\Domain\Transport\Enums\PickupLocationType;
use App\Domain\Transport\Enums\PickupStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Transport\TransportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An individual pickup of a convert (08 §8.7). The pickup point is copied
 * at request time so the sheet does not change with later edits.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $activity_transport_request_id
 * @property int|null $trip_id
 * @property int $convert_id
 * @property int|null $activity_plan_id
 * @property PickupLocationType $pickup_location_type
 * @property int|null $residence_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $pickup_address
 * @property CarbonImmutable $pickup_at
 * @property int|null $contact_person_id
 * @property int|null $vehicle_id
 * @property int|null $driver_id
 * @property PickupStatus $status
 * @property int $requested_by
 * @property int|null $disclosure_approved_by
 * @property string|null $notes
 */
#[UseFactory(TransportRequestFactory::class)]
final class TransportRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TransportRequestFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pickup_location_type' => PickupLocationType::class,
            'pickup_at' => 'immutable_datetime',
            'status' => PickupStatus::class,
        ];
    }
}
