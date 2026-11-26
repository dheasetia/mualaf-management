<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\GuidanceClasses\Pages\ViewGuidanceClass;
use App\Filament\Center\Resources\GuidanceClasses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Center\Resources\GuidanceClasses\RelationManagers\SessionsRelationManager;
use App\Filament\Center\Resources\GuidancePrograms\Pages\ListGuidancePrograms;
use Database\Seeders\GuidanceProgramSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->seed(GuidanceProgramSeeder::class);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->coordinator = grantRole(memberOf($this->dammam), 'guidance_coordinator', $this->dammam);
    $this->teacher = grantRole(memberOf($this->dammam), 'teacher', $this->dammam);
    $this->national = GuidanceProgram::query()->whereNull('tenant_id')->sole();

    [$this->mine, $this->theirs] = inTenant($this->dammam, function (): array {
        $mine = GuidanceClass::factory()->create(['program_id' => $this->national->id, 'name' => 'Tagalog morning class']);
        $theirs = GuidanceClass::factory()->create(['program_id' => $this->national->id, 'name' => 'Urdu evening class']);
        app(ManageClasses::class)->assignTeacher($mine, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);

        return [$mine, $theirs];
    });
});

it('gives each office role the guidance screens it needs', function (string $role, int $status): void {
    $this->actingAs(grantRole(memberOf($this->dammam), $role, $this->dammam))
        ->get('/center/DMM/guidance-classes')
        ->assertStatus($status);
})->with([
    'guidance coordinator' => ['guidance_coordinator', 200],
    'teacher' => ['teacher', 200],
    'local auditor' => ['local_auditor', 200],
    'registration officer' => ['registration_officer', 403],
]);

it('shows a teacher only the classes they teach', function (): void {
    $this->actingAs($this->teacher)->get('/center/DMM/guidance-classes')
        ->assertSee('Tagalog morning class')
        ->assertDontSee('Urdu evening class');

    $this->actingAs($this->teacher)->get("/center/DMM/guidance-classes/{$this->theirs->getRouteKey()}")->assertNotFound();
    $this->actingAs($this->coordinator)->get("/center/DMM/guidance-classes/{$this->theirs->getRouteKey()}")->assertOk();
});

it('enrolls from the class and lets its teacher take attendance', function (): void {
    $convert = inTenant($this->dammam, fn () => Convert::factory()->create());

    inCenterPanel($this->dammam, $this->coordinator);
    Livewire::test(EnrollmentsRelationManager::class, ['ownerRecord' => $this->mine, 'pageClass' => ViewGuidanceClass::class])
        ->callAction(TestAction::make('enroll')->table(), ['convert_id' => $convert->id])
        ->assertHasNoActionErrors();

    $enrollment = inTenant($this->dammam, fn () => Enrollment::query()->sole());

    inCenterPanel($this->dammam, $this->teacher);
    $page = Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->mine, 'pageClass' => ViewGuidanceClass::class])
        ->callAction(TestAction::make('schedule')->table(), ['starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString()])
        ->assertHasNoActionErrors();

    $session = inTenant($this->dammam, fn () => ClassSession::query()->sole());
    $page->callAction(TestAction::make('attendance')->table($session), ['rows' => [['enrollment_id' => $enrollment->id, 'status' => 'late']]])
        ->assertHasNoActionErrors();

    expect(inTenant($this->dammam, fn () => Attendance::query()->sole()->status->value))->toBe('late');
});

it('copies the national program into the office from the programs list', function (): void {
    inCenterPanel($this->dammam, $this->coordinator);

    Livewire::test(ListGuidancePrograms::class)
        ->callAction(TestAction::make('copy')->table($this->national), ['code' => 'DMM-NM'])
        ->assertHasNoActionErrors();

    expect(inTenant($this->dammam, fn () => GuidanceProgram::query()->where('tenant_id', $this->dammam->id)->sole()->based_on_program_id))->toBe($this->national->id);
});
