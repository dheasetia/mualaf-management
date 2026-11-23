<?php

declare(strict_types=1);

namespace App\Domain\Transport\Services;

use App\Domain\Converts\Services\PickupDirectory;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\TransportTrip;

/**
 * The driver's pickup sheet (08 §8.7, 13 §13.7): time, nickname, pickup
 * point and contact person's phone only, no religion or shahadah details
 * and no activity title. High-confidentiality converts are left out unless
 * Head of Dawah approved that pickup (04 §4.4); the count of omitted lines
 * is shown so dispatch can follow up.
 */
final readonly class PickupSheet
{
    public function __construct(private PickupDirectory $directory) {}

    /**
     * @return array{trip: array{departs_at: string, returns_at: string, vehicle: string, driver: string, destination: string|null}, lines: list<array{time: string, nickname: string, address: string|null, map: string|null, contact_name: string|null, contact_phone: string|null}>, withheld: int}
     */
    public function for(TransportTrip $trip): array
    {
        $trip->loadMissing(['vehicle', 'driver']);
        $timezone = (string) config('app.display_timezone');
        $pickups = $trip->pickups()->whereIn('status', [PickupStatus::Assigned, PickupStatus::PickedUp])->orderBy('pickup_at')->get();
        $lines = [];
        $withheld = 0;

        foreach ($pickups as $pickup) {
            $entry = $this->directory->sheetEntry($pickup->convert_id, $pickup->contact_person_id);

            if ($entry['high_confidentiality'] && $pickup->disclosure_approved_by === null) {
                $withheld++;

                continue;
            }

            $lines[] = [
                'time' => $pickup->pickup_at->setTimezone($timezone)->format('H:i'),
                'nickname' => $entry['nickname'],
                'address' => $pickup->pickup_address,
                'map' => $this->map($pickup),
                'contact_name' => $entry['contact_name'],
                'contact_phone' => $entry['contact_phone'],
            ];
        }

        return [
            'trip' => [
                'departs_at' => $trip->departs_at->setTimezone($timezone)->format('Y-m-d H:i'),
                'returns_at' => $trip->returns_at->setTimezone($timezone)->format('Y-m-d H:i'),
                'vehicle' => $trip->vehicle->plate_number,
                'driver' => $trip->driver->name,
                'destination' => $this->destination($pickups->first()),
            ],
            'lines' => $lines,
            'withheld' => $withheld,
        ];
    }

    private function map(TransportRequest $pickup): ?string
    {
        return $pickup->latitude === null || $pickup->longitude === null ? null
            : 'https://maps.google.com/?q='.$pickup->latitude.','.$pickup->longitude;
    }

    /** The activity's place, never its title. */
    private function destination(?TransportRequest $pickup): ?string
    {
        $planId = $pickup?->activity_plan_id;

        return $planId === null ? null : DawahActivityPlan::query()->whereKey($planId)->value('location');
    }
}
