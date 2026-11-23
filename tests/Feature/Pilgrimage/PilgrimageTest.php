<?php

declare(strict_types=1);

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Pilgrimage\Actions\ManagePrograms;
use App\Domain\Pilgrimage\Actions\RegistrationWorkflow;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Enums\RequirementStatus;
use App\Domain\Pilgrimage\Models\PilgrimageEvent;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
    Storage::fake('local');
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create();
    $this->officer = grantRole(memberOf($this->tenant), 'pilgrimage_officer', $this->tenant);
    $this->approver = grantRole(memberOf($this->tenant), 'approver', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->workflow = fn (): RegistrationWorkflow => app(RegistrationWorkflow::class);
    $this->program = fn (array $overrides = []) => ($this->in)(fn () => PilgrimageProgram::factory()->create($overrides));
    $this->eligible = function (PilgrimageProgram $program, string $identity) {
        $convert = documentsConvert($this->tenant, identity: $identity);

        return ($this->in)(function () use ($program, $convert): PilgrimageRegistration {
            $registration = ($this->workflow)()->register($program, $convert, $this->officer);

            return ($this->workflow)()->checkEligibility($registration, $this->officer);
        });
    };
});

it('registers converts once per program while registration is open', function (): void {
    $program = ($this->program)();
    $convert = documentsConvert($this->tenant);

    ($this->in)(function () use ($program, $convert): void {
        expect(($this->workflow)()->register($program, $convert, $this->officer)->status)->toBe(RegistrationStatus::Applied);
        expectRuleViolation(fn () => ($this->workflow)()->register($program, $convert, $this->officer), 'convert_id');

        $closed = PilgrimageProgram::factory()->create(['registration_deadline' => now()->subDay()->toDateString()]);
        expectRuleViolation(fn () => ($this->workflow)()->register($closed, $convert, $this->officer), 'program_id');

        $convert->forceFill(['status' => ConvertStatus::Deceased])->save();
        expectRuleViolation(fn () => ($this->workflow)()->register(PilgrimageProgram::factory()->create(), $convert, $this->officer), 'convert_id');
    });
});

it('checks eligibility rules: verified shahadah, months since shahadah and completed guidance', function (): void {
    $program = ($this->program)(['eligibility_rules' => ['min_months_since_shahadah' => 12, 'require_completed_guidance' => true]]);
    $unverified = documentsConvert($this->tenant, verified: false, identity: '277001155');

    $notes = ($this->in)(function () use ($program, $unverified): string {
        $registration = ($this->workflow)()->checkEligibility(($this->workflow)()->register($program, $unverified, $this->officer), $this->officer);
        expect($registration->status)->toBe(RegistrationStatus::Ineligible);

        return (string) $registration->eligibility_notes;
    });

    expect($notes)->toContain(__('pilgrimage.eligibility.shahadah_not_verified'))
        ->toContain(__('pilgrimage.eligibility.guidance_not_completed'));

    $recent = ($this->eligible)($program, '277001166');
    expect($recent->status)->toBe(RegistrationStatus::Ineligible)
        ->and($recent->eligibility_notes)->toContain(__('pilgrimage.eligibility.too_recent', ['months' => 12]));
});

it('waits for required documents, fulfilled or waived, before a registration is eligible', function (): void {
    $program = ($this->program)();
    ($this->in)(fn () => app(ManagePrograms::class)->addRequirement($program, 'passport', 'Valid passport copy', true, $this->officer));

    $registration = ($this->eligible)($program, '277001177');
    expect($registration->status)->toBe(RegistrationStatus::EligibilityChecked);

    ($this->in)(function () use ($registration): void {
        $row = $registration->requirements()->sole();
        expectRuleViolation(fn () => ($this->workflow)()->fulfil($row, $this->officer), 'status');

        $path = tempnam(sys_get_temp_dir(), 'pass');
        file_put_contents($path, "%PDF-1.4\n%passport\n");
        ($this->workflow)()->attach($row, $path, 'passport.pdf', $this->officer);
        ($this->workflow)()->fulfil($row->refresh(), $this->officer);

        expect($row->refresh()->status)->toBe(RequirementStatus::Fulfilled)
            ->and(($this->workflow)()->checkEligibility($registration, $this->officer)->status)->toBe(RegistrationStatus::Eligible);
    });
});

it('approves into free seats only, waitlists the rest, and frees a seat on withdrawal', function (): void {
    $program = ($this->program)(['capacity' => 1]);
    $first = ($this->eligible)($program, '277001188');
    $second = ($this->eligible)($program, '277001199');

    ($this->in)(function () use ($first, $second): void {
        expect(fn () => ($this->workflow)()->approve($first, $this->officer))->toThrow(AuthorizationException::class);
        expect(($this->workflow)()->approve($first, $this->approver)->status)->toBe(RegistrationStatus::Approved)
            ->and(($this->workflow)()->approve($second, $this->approver)->status)->toBe(RegistrationStatus::Waitlisted);
        expectRuleViolation(fn () => ($this->workflow)()->approve($second, $this->approver), 'status');

        expectRuleViolation(fn () => ($this->workflow)()->withdraw($first, '', $this->officer), 'reason');
        ($this->workflow)()->withdraw($first, 'Family emergency', $this->officer);
        expect(($this->workflow)()->approve($second, $this->approver)->status)->toBe(RegistrationStatus::Approved);
    });
});

it('does not let the registrar approve', function (): void {
    $program = ($this->program)();
    $both = grantRole($this->officer, 'approver', $this->tenant);
    $registration = ($this->eligible)($program, '277001200');

    ($this->in)(fn () => expectRuleViolation(fn () => ($this->workflow)()->approve($registration, $both), 'status'));
});

it('groups approved pilgrims within group capacity and moves confirmed pilgrims with the program', function (): void {
    $program = ($this->program)(['capacity' => 5]);
    $registrations = collect(['277001211', '277001222'])->map(fn (string $identity) => ($this->eligible)($program, $identity));

    ($this->in)(function () use ($program, $registrations): void {
        $registrations->each(fn ($registration) => ($this->workflow)()->approve($registration, $this->approver));
        $group = app(ManagePrograms::class)->createGroup($program, 'Group A', $this->officer, 1, $this->officer);

        app(ManagePrograms::class)->addToGroup($group, $registrations[0]->refresh(), $this->officer);
        expectRuleViolation(fn () => app(ManagePrograms::class)->addToGroup($group, $registrations[1]->refresh(), $this->officer), 'group_id');

        ($this->workflow)()->confirm($registrations[0], $this->officer);
        app(ManagePrograms::class)->changeStatus($program, ProgramStatus::Closed, $this->officer);
        app(ManagePrograms::class)->changeStatus($program, ProgramStatus::InProgress, $this->officer);
        expect($registrations[0]->refresh()->status)->toBe(RegistrationStatus::Departed);

        app(ManagePrograms::class)->changeStatus($program, ProgramStatus::Completed, $this->officer);
        expect($registrations[0]->refresh()->status)->toBe(RegistrationStatus::Completed)
            ->and($registrations[1]->refresh()->status)->toBe(RegistrationStatus::Approved)
            ->and(PilgrimageEvent::query()->where('program_id', $program->id)->count())->toBeGreaterThan(5);
    });
});

it('never lowers capacity below the seats taken', function (): void {
    $program = ($this->program)(['capacity' => 2]);
    foreach (['277001233', '277001244'] as $identity) {
        $registration = ($this->eligible)($program, $identity);
        ($this->in)(fn () => ($this->workflow)()->approve($registration, $this->approver));
    }

    ($this->in)(fn () => expectRuleViolation(fn () => app(ManagePrograms::class)->save($program->refresh(), [
        'program_type' => 'umrah', 'name' => 'x', 'season_year' => 2027, 'capacity' => 1,
        'starts_on' => $program->starts_on->toDateString(), 'ends_on' => $program->ends_on->toDateString(), 'registration_deadline' => $program->registration_deadline->toDateString(),
    ], $this->officer), 'capacity'));
});
