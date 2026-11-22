<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Examinations\Actions\ManageExams;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamComponent;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Exams\Pages\ViewExam;
use App\Filament\Center\Resources\Exams\RelationManagers\AttemptsRelationManager;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->officer = withAuthenticator(grantRole(memberOf($this->tenant), 'examination_officer', $this->tenant));
    $this->teacher = withAuthenticator(grantRole(memberOf($this->tenant), 'teacher', $this->tenant));
    $coordinator = grantRole(memberOf($this->tenant), 'guidance_coordinator', $this->tenant);

    [$this->exam, $this->otherExam, $this->attempt] = inTenant($this->tenant, function () use ($coordinator): array {
        $program = GuidanceProgram::factory()->create();
        $class = GuidanceClass::factory()->create(['program_id' => $program->id]);
        $otherClass = GuidanceClass::factory()->create(['program_id' => $program->id]);
        app(ManageClasses::class)->assignTeacher($class, $this->teacher->id, TeacherRole::LeadTeacher, $coordinator);
        $enrollment = app(ManageEnrollments::class)->enroll(Convert::factory()->create()->id, $program->id, $class->id, $coordinator);

        $make = fn (int $classId, string $title) => app(ManageExams::class)->save(null, [
            'program_id' => $program->id, 'class_id' => $classId, 'title' => $title, 'exam_type' => 'final', 'maximum_score' => 100, 'passing_score' => 60,
        ], [['component_code' => 'W', 'name' => 'Written', 'max_score' => 100, 'weight' => 1]], $this->officer);

        $exam = $make($class->id, 'Tagalog class final');
        $other = $make($otherClass->id, 'Urdu class final');
        $attempt = app(ManageExams::class)->addAttempts($exam, [$enrollment->id], $this->officer)[0];
        app(ManageExams::class)->start($exam, $this->officer);

        return [$exam, $other, $attempt];
    });
});

it('opens exams to examination officers and to teachers for their own classes', function (): void {
    $this->actingAs($this->officer)->get('/center/DMM/exams')->assertOk()->assertSee(['Tagalog class final', 'Urdu class final']);
    $this->actingAs($this->teacher)->get('/center/DMM/exams')->assertOk()->assertSee('Tagalog class final')->assertDontSee('Urdu class final');
    $this->actingAs(withAuthenticator(grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant)))->get('/center/DMM/exams')->assertForbidden();
});

it('lets the teacher grade and the officer finalize from the exam page', function (): void {
    $component = inTenant($this->tenant, fn () => ExamComponent::query()->where('exam_id', $this->exam->id)->sole());

    inCenterPanel($this->tenant, $this->teacher);
    Livewire::test(AttemptsRelationManager::class, ['ownerRecord' => $this->exam, 'pageClass' => ViewExam::class])
        ->callAction(TestAction::make('grade')->table($this->attempt), ['score_'.$component->id => 72])
        ->assertHasNoActionErrors();

    Livewire::test(ViewExam::class, ['record' => $this->exam->getRouteKey()])->callAction('submit');

    inCenterPanel($this->tenant, $this->officer);
    Livewire::test(ViewExam::class, ['record' => $this->exam->getRouteKey()])->callAction('finalize');

    inTenant($this->tenant, fn () => expect($this->exam->refresh()->status)->toBe(ExamStatus::Finalized)
        ->and(ExamResult::query()->where('exam_attempt_id', ExamAttempt::query()->sole()->id)->sole()->final_score)->toBe('72.00'));
});
