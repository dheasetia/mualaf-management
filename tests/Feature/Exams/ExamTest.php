<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Models\Convert;
use App\Domain\Examinations\Actions\ExamFinalization;
use App\Domain\Examinations\Actions\GradeAttempt;
use App\Domain\Examinations\Actions\ManageExams;
use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamComponent;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->officer = grantRole(memberOf($this->tenant), 'examination_officer', $this->tenant);
    $this->coordinator = grantRole(memberOf($this->tenant), 'guidance_coordinator', $this->tenant);
    $this->teacher = grantRole(memberOf($this->tenant), 'teacher', $this->tenant);
    $this->otherTeacher = grantRole(memberOf($this->tenant), 'teacher', $this->tenant);

    $this->program = GuidanceProgram::factory()->create();
    $this->class = GuidanceClass::factory()->create(['program_id' => $this->program->id]);
    $otherClass = GuidanceClass::factory()->create(['program_id' => $this->program->id]);
    app(ManageClasses::class)->assignTeacher($this->class, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    app(ManageClasses::class)->assignTeacher($otherClass, $this->otherTeacher->id, TeacherRole::LeadTeacher, $this->coordinator);

    $this->enrollment = app(ManageEnrollments::class)->enroll(Convert::factory()->create()->id, $this->program->id, $this->class->id, $this->coordinator);

    $this->exam = app(ManageExams::class)->save(null, [
        'program_id' => $this->program->id, 'class_id' => $this->class->id, 'title' => 'Final', 'exam_type' => 'final',
        'maximum_score' => 100, 'passing_score' => 60,
    ], [
        ['component_code' => 'W', 'name' => 'Written', 'max_score' => 50, 'weight' => 2],
        ['component_code' => 'O', 'name' => 'Oral', 'max_score' => 20, 'weight' => 1],
    ], $this->officer);
    [$this->written, $this->oral] = ExamComponent::query()->orderBy('id')->get()->all();
    $this->attempt = app(ManageExams::class)->addAttempts($this->exam, [$this->enrollment->id], $this->officer)[0];
    app(ManageExams::class)->start($this->exam, $this->officer);
    $this->scores = fn (float $written, float $oral): array => [$this->written->id => $written, $this->oral->id => $oral];
});

it('grades with weighted components and marks the exam graded when nobody is left', function (): void {
    $result = app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(40, 18), $this->teacher);

    expect($result)
        ->final_score->toBe('83.33')
        ->passed->toBeTrue()
        ->status->toBe(ResultStatus::Provisional)
        ->and($this->exam->refresh()->status)->toBe(ExamStatus::Graded);
});

it('lets teachers grade only candidates of the classes they teach', function (): void {
    expectRuleViolation(fn () => app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(40, 18), $this->otherTeacher), 'scores');
});

it('requires every component within its maximum', function (): void {
    expectRuleViolation(fn () => app(GradeAttempt::class)->grade($this->attempt, [$this->written->id => 40], $this->teacher), 'scores');
    expectRuleViolation(fn () => app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(55, 18), $this->teacher), 'scores');
});

it('finalizes results, keeps them immutable and copies the best score to the enrollment', function (): void {
    app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(40, 18), $this->teacher);
    app(ExamFinalization::class)->submit($this->exam, $this->teacher);
    app(ExamFinalization::class)->finalize($this->exam, $this->officer);

    $result = ExamResult::query()->sole();
    expect($result->status)->toBe(ResultStatus::Finalized)
        ->and($this->attempt->refresh()->status)->toBe(AttemptStatus::Finalized)
        ->and(Enrollment::query()->find($this->enrollment->id)->final_score)->toBe('83.33');

    expect(fn () => $result->forceFill(['final_score' => 99])->save())->toThrow(LogicException::class);
    expectRuleViolation(fn () => app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(50, 20), $this->officer), 'status');
});

it('corrects a finalized result only by an audited amendment with a reason', function (): void {
    app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(20, 10), $this->teacher);
    app(ExamFinalization::class)->submit($this->exam, $this->teacher);
    app(ExamFinalization::class)->finalize($this->exam, $this->officer);
    $result = ExamResult::query()->sole();
    expect($result->passed)->toBeFalse();

    expectRuleViolation(fn () => app(ExamFinalization::class)->amend($result, 65, $this->officer, ''), 'reason');
    app(ExamFinalization::class)->amend($result, 65, $this->officer, 'Oral part was mis-recorded');

    expect($result->refresh())
        ->status->toBe(ResultStatus::Amended)
        ->passed->toBeTrue()
        ->and(Enrollment::query()->find($this->enrollment->id)->final_score)->toBe('65.00')
        ->and(AuditLog::query()->where('action', 'exams.result_amended')->sole()->new_values_redacted['reason'])->toBe('Oral part was mis-recorded');
});

it('allows a second attempt only after a failed one is closed', function (): void {
    expectRuleViolation(fn () => app(ManageExams::class)->addAttempts($this->exam, [$this->enrollment->id], $this->officer), 'enrollment_ids');

    app(GradeAttempt::class)->grade($this->attempt, ($this->scores)(10, 5), $this->teacher);
    $retry = Exam::query()->findOrFail($this->exam->id);
    $retry->forceFill(['status' => ExamStatus::InProgress])->save();

    expectRuleViolation(fn () => app(ManageExams::class)->addAttempts($retry, [$this->enrollment->id], $this->officer), 'enrollment_ids');

    app(GradeAttempt::class)->markAbsent($this->attempt->refresh(), $this->officer);
    expect(app(ManageExams::class)->addAttempts($retry, [$this->enrollment->id], $this->officer)[0]->attempt_number)->toBe(2);
});

it('refuses candidates outside the exam class or program', function (): void {
    $outsider = app(ManageEnrollments::class)->enroll(Convert::factory()->create()->id, $this->program->id, null, $this->coordinator);

    expectRuleViolation(fn () => app(ManageExams::class)->addAttempts($this->exam, [$outsider->id], $this->officer), 'enrollment_ids');
});
