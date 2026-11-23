<?php

declare(strict_types=1);

use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Dawah\Actions\ManageParticipants;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\ParticipantDirectory;
use App\Domain\Platform\Models\Religion;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transport\Actions\Dispatch;
use App\Domain\Transport\Actions\PickupRequests;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\Vehicle;
use App\Domain\Transport\Services\PickupSheet;
use Illuminate\Auth\Access\AuthorizationException;

/*
 * Critical test #14 (14-testing): a high-confidentiality convert does not
 * appear on printed participant lists or pickup sheets, and pickup sheets
 * never carry religion or shahadah details.
 */
beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    $this->officer = grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);

    $religion = Religion::factory()->create(['name_en' => 'Catholic Christianity', 'name_ar' => 'المسيحية الكاثوليكية']);
    [$this->trip, $this->open, $this->secret, $this->plan] = ($this->in)(function () use ($religion): array {
        $plan = DawahActivityPlan::factory()->create(['title' => 'New Muslims gathering', 'location' => 'Community hall']);
        $open = convertWithPickupPoint(['previous_religion_id' => $religion->id]);
        $secret = convertWithPickupPoint(['full_name' => 'Hidden Person', 'islamic_name' => 'Hidden', 'disclosure_risk_level' => DisclosureLevel::HighConfidentiality]);
        $start = now()->addDay()->setTime(8, 0);
        $trip = app(Dispatch::class)->createTrip([
            'vehicle_id' => Vehicle::factory()->create()->id, 'driver_id' => Driver::factory()->create()->id,
            'departs_at' => $start->toIso8601String(), 'returns_at' => $start->copy()->addHours(3)->toIso8601String(),
        ], $this->officer);

        foreach ([$open, $secret] as $person) {
            $pickup = TransportRequest::factory()->create([
                'convert_id' => $person['convert']->id, 'pickup_location_type' => 'residence', 'residence_id' => $person['residence']->id,
                'pickup_address' => 'Camp 4, Block B', 'latitude' => '26.42', 'longitude' => '50.08',
                'contact_person_id' => $person['contact']->id, 'activity_plan_id' => $plan->id, 'pickup_at' => $start->copy()->addMinutes(20),
            ]);
            app(Dispatch::class)->assign($pickup, $trip, $this->officer);
        }

        return [$trip, $open, $secret, $plan];
    });
    $this->sheet = fn (): array => ($this->in)(fn () => app(PickupSheet::class)->for($this->trip->refresh()));
});

it('prints only nickname, pickup point and contact, without religion, shahadah or the activity title', function (): void {
    $sheet = ($this->sheet)();
    $printed = json_encode($sheet, JSON_UNESCAPED_UNICODE);

    expect($sheet['lines'])->toHaveCount(1)
        ->and($sheet['lines'][0])->toMatchArray(['nickname' => 'Dawud', 'contact_name' => 'Camp supervisor', 'contact_phone' => '+966501112233'])
        ->and(array_keys($sheet['lines'][0]))->toBe(['time', 'nickname', 'address', 'map', 'contact_name', 'contact_phone'])
        ->and($sheet['trip']['destination'])->toBe('Community hall')
        ->and($printed)->not->toContain('Daniel Mendoza')
        ->not->toContain('Catholic')
        ->not->toContain('New Muslims gathering')
        ->not->toContain('Hidden')
        ->not->toContain('2026-11-01');
});

it('leaves a high-confidentiality convert off the sheet until Head of Dawah allows that pickup', function (): void {
    expect(($this->sheet)()['withheld'])->toBe(1);

    $pickup = ($this->in)(fn () => TransportRequest::query()->where('convert_id', $this->secret['convert']->id)->sole());
    ($this->in)(fn () => expect(fn () => app(PickupRequests::class)->approveDisclosure($pickup, $this->officer))->toThrow(AuthorizationException::class));
    ($this->in)(fn () => app(PickupRequests::class)->approveDisclosure($pickup, $this->head));

    $sheet = ($this->sheet)();
    expect($sheet['withheld'])->toBe(0)
        ->and(array_column($sheet['lines'], 'nickname'))->toContain('Hidden');
});

it('leaves a high-confidentiality convert off printed participant lists', function (): void {
    ($this->in)(function (): void {
        foreach ([$this->open, $this->secret] as $person) {
            app(ManageParticipants::class)->add($this->plan, ['participant_type' => 'convert', 'participant_id' => $person['convert']->id], $this->head);
        }

        expect(array_column(app(ParticipantDirectory::class)->printable($this->plan, $this->head), 'name'))->toBe(['Daniel Mendoza']);
    });
});
