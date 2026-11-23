<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Filament\Center\Resources\Trips\Pages\ManageTrips;
use App\Filament\Center\Resources\Vehicles\Pages\ManageVehicles;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->officer = withAuthenticator(grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant));
});

it('registers a vehicle and refuses a duplicate plate in the same center', function (): void {
    inCenterPanel($this->tenant, $this->officer);
    $vehicle = ['vehicle_number' => 'V-1', 'plate_number' => 'abc 1234', 'vehicle_type' => 'van', 'capacity' => 7, 'status' => 'available'];

    Livewire::test(ManageVehicles::class)->callAction('create', $vehicle)->assertHasNoActionErrors();
    Livewire::test(ManageVehicles::class)->callAction('create', $vehicle)->assertHasActionErrors(['plate_number']);

    expect(inTenant($this->tenant, fn () => Vehicle::query()->sole()->plate_number))->toBe('ABC 1234');
});

it('plans a trip and opens its pickup sheet', function (): void {
    inCenterPanel($this->tenant, $this->officer);
    [$vehicle, $driver] = inTenant($this->tenant, fn () => [Vehicle::factory()->create(), Driver::factory()->create()]);

    Livewire::test(ManageTrips::class)
        ->callAction(TestAction::make('newTrip')->table(), [
            'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'departs_at' => now()->addDay()->format('Y-m-d H:i'), 'returns_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i'),
        ])
        ->assertHasNoActionErrors();

    $trip = inTenant($this->tenant, fn () => TransportTrip::query()->sole());
    $this->get("/center/DMM/trips/{$trip->id}/sheet")->assertOk()->assertSee(__('transport.sheet.empty'));
});

it('keeps transport screens away from roles without transport permissions', function (): void {
    $this->actingAs(grantRole(memberOf($this->tenant), 'teacher', $this->tenant))->get('/center/DMM/trips')->assertForbidden();
});
