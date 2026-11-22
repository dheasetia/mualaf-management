<?php

declare(strict_types=1);

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dai\Models\DaiAssignment;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamResult;
use App\Domain\Guidance\Models\Attendance;
use App\Domain\Guidance\Models\ClassSession;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\LearningProgress;
use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Models\ProspectStatusHistory;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

// 14-testing §14.4: every tenant-scoped model must be listed here, with its factory.
const TENANT_SCOPED_MODELS = [
    'dai' => Dai::class,
    'dai assignment' => DaiAssignment::class,
    'dai follow-up' => DaiFollowUp::class,
    'prospect' => Prospect::class,
    'prospect status history' => ProspectStatusHistory::class,
    'duplicate review case' => DuplicateReviewCase::class,
    'guidance class' => GuidanceClass::class,
    'class session' => ClassSession::class,
    'attendance' => Attendance::class,
    'learning progress' => LearningProgress::class,
    'exam' => Exam::class,
    'exam attempt' => ExamAttempt::class,
    'exam result' => ExamResult::class,
    'certificate' => Certificate::class,
];

dataset('tenant_scoped_models', array_map(
    fn (string $model): array => [$model, fn (): Factory => $model::factory()],
    TENANT_SCOPED_MODELS,
));

it('hides, and refuses to change, records of another tenant', function (string $model, Closure $factory): void {
    [$tenantA, $tenantB] = Tenant::factory()->active()->count(2)->create();
    $recordOfB = inTenant($tenantB, fn () => $factory()->create());

    inTenant($tenantA, function () use ($model, $recordOfB): void {
        expect($model::query()->find($recordOfB->getKey()))->toBeNull()
            ->and($model::query()->count())->toBe(0)
            ->and($model::query()->whereKey($recordOfB->getKey())->toBase()->update(['id' => DB::raw('id')]))->toBe(0)
            ->and($model::query()->whereKey($recordOfB->getKey())->delete())->toBe(0);
    });

    expect(inTenant($tenantB, fn () => $model::query()->find($recordOfB->getKey())))->not->toBeNull();
})->with('tenant_scoped_models')->group('tenant-isolation');

it('lists every model that uses BelongsToTenant', function (): void {
    $scoped = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $class = 'App\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString();

        if (class_exists($class) && in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
            $scoped[] = $class;
        }
    }

    expect($scoped)->toEqualCanonicalizing(array_values(TENANT_SCOPED_MODELS));
})->group('tenant-isolation');
