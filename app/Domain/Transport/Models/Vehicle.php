<?php

declare(strict_types=1);

namespace App\Domain\Transport\Models;

use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Enums\VehicleType;
use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\Transport\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $vehicle_number
 * @property string $plate_number
 * @property VehicleType $vehicle_type
 * @property int $capacity
 * @property VehicleStatus $status
 */
#[UseFactory(VehicleFactory::class)]
final class Vehicle extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'capacity' => 'integer',
            'status' => VehicleStatus::class,
        ];
    }
}
