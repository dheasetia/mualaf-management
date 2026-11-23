<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Guidance\Actions\ClassRecords;
use App\Domain\Guidance\Actions\ManageClasses;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Actions\ManagePrograms;
use App\Domain\Guidance\Enums\AttendanceStatus;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Enums\GenderPolicy;
use App\Domain\Guidance\Enums\ProgressSource;
use App\Domain\Guidance\Enums\TeacherRole;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Models\LearningProgress;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\GuidanceProgramSeeder;

beforeEach(function (): void {
    seedRbac();
    $this->seed(GuidanceProgramSeeder::class);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    app(TenantContext::class)->set($this->dammam);

    $this->coordinator = grantRole(memberOf($this->dammam), 'guidance_coordinator', $this->dammam);
    $this->teacher = grantRole(memberOf($this->dammam), 'teacher', $this->dammam);
    $this->otherTeacher = grantRole(memberOf($this->dammam), 'teacher', $this->dammam);

    $this->national = GuidanceProgram::query()->whereNull('tenant_id')->sole();
    $this->class = fn (array $overrides = []): GuidanceClass => GuidanceClass::factory()->create(['program_id' => $this->national->id, ...$overrides]);
    $this->enroll = fn (Convert $convert, ?GuidanceClass $class = null) => app(ManageEnrollments::class)->enroll($convert->id, $this->national->id, $class?->id, $this->coordinator);
});

it('copies the national program with its modules and lessons into the office', function (): void {
    $copy = app(ManagePrograms::class)->copyNational($this->national, 'DMM-NM', $this->coordinator);

    expect($copy)
        ->tenant_id->toBe($this->dammam->id)
        ->based_on_program_id->toBe($this->national->id)
        ->and($copy->modules()->count())->toBe(10)
        ->and($copy->lessons()->count())->toBe($this->national->lessons()->count());

    expectRuleViolation(fn () => app(ManagePrograms::class)->save($this->national, ['name_ar' => 'x'], $this->coordinator), 'program_id');
});

it('enrolls a convert once per program, within capacity and gender policy', function (): void {
    $class = ($this->class)(['capacity' => 1, 'gender_policy' => GenderPolicy::Male]);
    $first = Convert::factory()->create(['gender' => Gender::Male]);

    $enrollment = ($this->enroll)($first, $class);
    expect($enrollment->status)->toBe(EnrollmentStatus::Enrolled)
        ->and($enrollment->target_completion_date->toDateString())->toBe(today()->addDays(90)->toDateString());

    expectRuleViolation(fn () => ($this->enroll)($first), 'program_id');
    expectRuleViolation(fn () => ($this->enroll)(Convert::factory()->create(['gender' => Gender::Female]), $class), 'class_id');
    expectRuleViolation(fn () => ($this->enroll)(Convert::factory()->create(['gender' => Gender::Male]), $class), 'class_id');
});

it('does not enroll in a class that is not open', function (): void {
    expectRuleViolation(fn () => ($this->enroll)(Convert::factory()->create(), ($this->class)(['status' => ClassStatus::Planned])), 'class_id');
});

it('lets only an assigned teacher record attendance and progress (critical test #6)', function (): void {
    $class = ($this->class)();
    $other = ($this->class)();
    app(ManageClasses::class)->assignTeacher($class, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    app(ManageClasses::class)->assignTeacher($other, $this->otherTeacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    $enrollment = ($this->enroll)(Convert::factory()->create(), $class);
    $session = app(ClassRecords::class)->scheduleSession($class, ['starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString()], $this->teacher);
    $lesson = GuidanceLesson::query()->firstOrFail();

    expectRuleViolation(fn () => app(ClassRecords::class)->recordAttendance($session, [$enrollment->id => AttendanceStatus::Present], $this->otherTeacher), 'class_id');
    expectRuleViolation(fn () => app(ClassRecords::class)->recordProgress($enrollment, $lesson->id, ['progress_status' => 'completed'], $this->otherTeacher), 'class_id');

    app(ClassRecords::class)->recordAttendance($session, [$enrollment->id => AttendanceStatus::Present], $this->teacher);
    app(ClassRecords::class)->recordProgress($enrollment, $lesson->id, ['progress_status' => 'completed', 'score' => 90], $this->teacher);

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->attendance_percentage)->toBe('100.00')
        ->and(LearningProgress::query()->sole()->score)->toBe('90.00');
});

it('calculates attendance as (present + late) over held sessions minus excused', function (): void {
    $class = ($this->class)();
    app(ManageClasses::class)->assignTeacher($class, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    $enrollment = ($this->enroll)(Convert::factory()->create(), $class);
    $records = app(ClassRecords::class);

    foreach ([AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Absent, AttendanceStatus::Excused] as $status) {
        $session = $records->scheduleSession($class, ['starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString()], $this->teacher);
        $records->recordAttendance($session, [$enrollment->id => $status], $this->teacher);
    }

    expect($enrollment->refresh()->attendance_percentage)->toBe('66.67');

    expectRuleViolation(fn () => app(ManageEnrollments::class)->changeStatus($enrollment, EnrollmentStatus::Completed, $this->coordinator), 'status');
    expectRuleViolation(fn () => app(ManageEnrollments::class)->changeStatus($enrollment, EnrollmentStatus::Withdrawn, $this->coordinator), 'reason');
});

it('lets a teacher see only the converts of the classes they teach', function (): void {
    $class = ($this->class)();
    app(ManageClasses::class)->assignTeacher($class, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    $inClass = Convert::factory()->create();
    $notInClass = Convert::factory()->create();
    ($this->enroll)($inClass, $class);

    expect(app(ConvertAccess::class)->visible(Convert::query(), $this->teacher)->pluck('id')->all())->toBe([$inClass->id])
        ->and(app(ConvertAccess::class)->canSee($this->teacher, $notInClass))->toBeFalse();
});

it('closes enrollments on transfer and carries completed lessons into the next one', function (): void {
    $riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $convert = Convert::factory()->create();
    $class = ($this->class)();
    app(ManageClasses::class)->assignTeacher($class, $this->teacher->id, TeacherRole::LeadTeacher, $this->coordinator);
    $old = ($this->enroll)($convert, $class);
    app(ClassRecords::class)->recordProgress($old, GuidanceLesson::query()->firstOrFail()->id, ['progress_status' => 'completed'], $this->teacher);

    app(ManageEnrollments::class)->closeForTransfer($convert->id, $this->dammam->id);
    expect($old->refresh()->status)->toBe(EnrollmentStatus::Transferred);

    // The convert now belongs to Riyadh (moved directly here; the transfer flow is tested in Transfers).
    DB::table('converts')->where('id', $convert->id)->update(['current_tenant_id' => $riyadh->id]);
    $coordinator = grantRole(memberOf($riyadh), 'guidance_coordinator', $riyadh);
    $new = inTenant($riyadh, fn () => app(ManageEnrollments::class)->enroll($convert->id, $this->national->id, null, $coordinator));

    expect(inTenant($riyadh, fn () => LearningProgress::query()->where('enrollment_id', $new->id)->sole()->source))->toBe(ProgressSource::Transferred)
        ->and(Enrollment::query()->withoutGlobalScopes()->find($old->id)->transferred_to_enrollment_id)->toBe($new->id);
})->group('tenant-isolation');
