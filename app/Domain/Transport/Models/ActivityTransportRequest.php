<?php

declare(strict_types=1);

namespace App\Domain\Transport\Models;

use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\TransportType;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Transport\ActivityTransportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Transport for a dawah activity plan (08 §8.7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $activity_plan_id
 * @property int $requested_by
 * @property TransportType $transport_type
 * @property int $vehicle_count
 * @property int $passenger_count
 * @property bool $pickup_required
 * @property CarbonImmutable $departure_at
 * @property CarbonImmutable|null $return_at
 * @property ActivityTransportStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 * @property string|null $notes
 */
#[UseFactory(ActivityTransportRequestFactory::class)]
final class ActivityTransportRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ActivityTransportRequestFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transport_type' => TransportType::class,
            'vehicle_count' => 'integer',
            'passenger_count' => 'integer',
            'pickup_required' => 'boolean',
            'departure_at' => 'immutable_datetime',
            'return_at' => 'immutable_datetime',
            'status' => ActivityTransportStatus::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
