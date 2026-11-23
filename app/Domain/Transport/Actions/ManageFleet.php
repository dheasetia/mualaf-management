<?php

declare(strict_types=1);

namespace App\Domain\Transport\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Transport\Enums\DriverStatus;
use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Enums\VehicleType;
use App\Domain\Transport\Exceptions\TransportRuleViolation;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\Vehicle;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Vehicles and drivers of the office (transport.fleet.manage).
 */
final readonly class ManageFleet
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function saveVehicle(?Vehicle $vehicle, array $data, User $actor): Vehicle
    {
        $this->authorize($actor);

        $vehicle ??= new Vehicle;
        $vehicle->vehicle_number = (string) $data['vehicle_number'];
        $vehicle->plate_number = mb_strtoupper(trim((string) $data['plate_number']));
        $vehicle->vehicle_type = VehicleType::from((string) $data['vehicle_type']);
        $vehicle->capacity = max(1, (int) $data['capacity']);
        $vehicle->status = VehicleStatus::from((string) ($data['status'] ?? VehicleStatus::Available->value));

        try {
            $vehicle->save();
        } catch (UniqueConstraintViolationException) {
            throw TransportRuleViolation::plateTaken();
        }

        $this->audit->recordChanges('vehicle.saved', $vehicle, actor: $actor);

        return $vehicle;
    }

    /** @param  array<string, mixed>  $data */
    public function saveDriver(?Driver $driver, array $data, User $actor): Driver
    {
        $this->authorize($actor);

        $driver ??= new Driver;
        $driver->name = (string) $data['name'];
        $driver->phone_encrypted = (string) PhoneNumber::normalize((string) $data['phone']);
        $driver->license_number_encrypted = blank($data['license_number'] ?? null) ? $driver->license_number_encrypted : trim((string) $data['license_number']);
        $driver->license_expires_on = blank($data['license_expires_on'] ?? null) ? null : CarbonImmutable::parse((string) $data['license_expires_on']);
        $driver->user_id = blank($data['user_id'] ?? null) ? null : (int) $data['user_id'];
        $driver->status = DriverStatus::from((string) ($data['status'] ?? DriverStatus::Active->value));
        $driver->save();

        $this->audit->recordChanges('driver.saved', $driver, actor: $actor);

        return $driver;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('transport.fleet.manage')) {
            throw new AuthorizationException;
        }
    }
}
