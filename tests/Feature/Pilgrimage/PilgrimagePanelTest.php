<?php

declare(strict_types=1);

use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\CreatePilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\Pages\ViewPilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\RelationManagers\RegistrationsRelationManager;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->officer = grantRole(memberOf($this->tenant), 'pilgrimage_officer', $this->tenant);
});

it('creates a program and registers a convert from the panel', function (): void {
    inCenterPanel($this->tenant, $this->officer);

    Livewire::test(CreatePilgrimageProgram::class)
        ->fillForm([
            'program_type' => 'umrah', 'name' => 'Umrah Rabi al-Awwal', 'season_year' => 2027, 'capacity' => 30,
            'starts_on' => now()->addMonths(2)->toDateString(), 'ends_on' => now()->addMonths(2)->addDays(9)->toDateString(),
            'registration_deadline' => now()->addMonth()->toDateString(), 'min_months_since_shahadah' => 6,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $program = inTenant($this->tenant, fn () => PilgrimageProgram::query()->sole());
    expect($program->eligibility_rules)->toBe(['min_months_since_shahadah' => 6, 'require_completed_guidance' => false]);

    inTenant($this->tenant, fn () => $program->forceFill(['status' => 'open'])->save());
    $convert = documentsConvert($this->tenant);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $program, 'pageClass' => ViewPilgrimageProgram::class])
        ->callAction(TestAction::make('register')->table(), ['convert_id' => $convert->id])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => PilgrimageRegistration::query()->sole()->convert_id))->toBe($convert->id);
});

it('refuses a deadline after the start on the program form', function (): void {
    inCenterPanel($this->tenant, $this->officer);

    Livewire::test(CreatePilgrimageProgram::class)
        ->fillForm([
            'program_type' => 'hajj', 'name' => 'Hajj', 'season_year' => 2027, 'capacity' => 10,
            'starts_on' => now()->addMonth()->toDateString(), 'ends_on' => now()->addMonth()->addDays(5)->toDateString(),
            'registration_deadline' => now()->addMonths(2)->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);
});

it('shows programs to pilgrimage.view holders only', function (): void {
    $this->actingAs($this->officer)->get('/center/DMM/pilgrimage')->assertOk();
    $this->actingAs(grantRole(memberOf($this->tenant), 'teacher', $this->tenant))->get('/center/DMM/pilgrimage')->assertForbidden();
});
