<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\ManagePlans;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transport\Actions\PilgrimPickups;
use App\Domain\Transport\Models\TransportRequest;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\ViewPilgrimageProgram;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    $this->officer = grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    [$this->program, $this->plan, $this->travelling] = ($this->in)(function (): array {
        $program = PilgrimageProgram::factory()->create(['status' => ProgramStatus::Open]);
        $register = fn (Convert $convert, RegistrationStatus $status) => PilgrimageRegistration::factory()->create(['program_id' => $program->id, 'convert_id' => $convert->id, 'status' => $status]);
        $travelling = [convertWithPickupPoint()['convert'], convertWithPickupPoint()['convert']];
        $register($travelling[0], RegistrationStatus::Approved);
        $register($travelling[1], RegistrationStatus::Confirmed);
        $register(convertWithPickupPoint()['convert'], RegistrationStatus::Waitlisted);
        $register(Convert::factory()->create(), RegistrationStatus::Approved);

        return [$program, DawahActivityPlan::factory()->create(['pilgrimage_program_id' => $program->id]), $travelling];
    });
    $this->pickupAt = CarbonImmutable::now()->addDays(3);
});

it('requests one pickup per travelling pilgrim with a residence, once per activity', function (): void {
    $result = ($this->in)(fn () => app(PilgrimPickups::class)->requestForProgram($this->program->id, $this->plan, $this->pickupAt, null, $this->officer));
    expect($result)->toBe(['created' => 2, 'skipped' => 1]);

    $again = ($this->in)(fn () => app(PilgrimPickups::class)->requestForProgram($this->program->id, $this->plan, $this->pickupAt, null, $this->officer));
    expect($again)->toBe(['created' => 0, 'skipped' => 3])
        ->and(($this->in)(fn () => TransportRequest::query()->where('activity_plan_id', $this->plan->id)->pluck('convert_id')->sort()->values()->all()))
        ->toBe(collect($this->travelling)->pluck('id')->sort()->values()->all());
});

it('only uses activities linked to the program and future pickup times', function (): void {
    $other = ($this->in)(fn () => DawahActivityPlan::factory()->create());

    ($this->in)(function () use ($other): void {
        expectRuleViolation(fn () => app(PilgrimPickups::class)->requestForProgram($this->program->id, $other, $this->pickupAt, null, $this->officer), 'activity_plan_id');
        expectRuleViolation(fn () => app(PilgrimPickups::class)->requestForProgram($this->program->id, $this->plan, CarbonImmutable::now()->subHour(), null, $this->officer), 'pickup_at');
    });
});

it('links an activity only to a running program of the office', function (): void {
    $finished = ($this->in)(fn () => PilgrimageProgram::factory()->create(['status' => ProgramStatus::Completed]));
    $daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    ($this->in)(fn () => Dai::factory()->create(['user_id' => $daiUser->id]));
    $data = fn (int $programId): array => ['title' => 'Umrah departure', 'objective' => 'Take the pilgrims to the airport', 'activity_type_id' => $this->plan->activity_type_id, 'planned_date' => now()->addWeek()->toDateString(), 'pilgrimage_program_id' => $programId];

    ($this->in)(function () use ($finished, $daiUser, $data): void {
        expectRuleViolation(fn () => app(ManagePlans::class)->save(null, $data($finished->id), $daiUser), 'pilgrimage_program_id');
        expect(app(ManagePlans::class)->save(null, $data($this->program->id), $daiUser)->pilgrimage_program_id)->toBe($this->program->id);
    });
});

it('requests the pickups from the program page', function (): void {
    inCenterPanel($this->tenant, $this->officer);

    Livewire::test(ViewPilgrimageProgram::class, ['record' => $this->program->getRouteKey()])
        ->callAction('requestPickups', ['activity_plan_id' => $this->plan->id, 'pickup_at' => $this->pickupAt->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i')])
        ->assertHasNoActionErrors();

    expect(($this->in)(fn () => TransportRequest::query()->count()))->toBe(2);
});
