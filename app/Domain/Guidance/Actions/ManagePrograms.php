<?php

declare(strict_types=1);

namespace App\Domain\Guidance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Guidance\Exceptions\GuidanceRuleViolation;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Domain\Guidance\Models\GuidanceModule;
use App\Domain\Guidance\Models\GuidanceProgram;
use App\Domain\Guidance\Models\LearningProgress;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Office programs (guidance.programs.manage). National programs are
 * templates (seeded); an office copies one to adapt it (based_on_program_id).
 */
final readonly class ManagePrograms
{
    private const array PROGRAM_FIELDS = ['code', 'name_ar', 'name_en', 'description', 'duration_days', 'minimum_attendance_percentage', 'passing_score', 'active'];

    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /** @param  array<string, mixed>  $data */
    public function save(?GuidanceProgram $program, array $data, User $actor): GuidanceProgram
    {
        $this->authorize($actor);
        $this->ensureOwn($program);

        return DB::transaction(function () use ($program, $data, $actor): GuidanceProgram {
            $program ??= new GuidanceProgram;
            $isNew = ! $program->exists;

            foreach (self::PROGRAM_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $program->setAttribute($field, $data[$field] === '' ? null : $data[$field]);
                }
            }

            if ($isNew) {
                $program->tenant_id = $this->tenantContext->requireId();
            }

            $program->save();
            $this->audit->record($isNew ? 'guidance.program_created' : 'guidance.program_updated', newValues: ['program_id' => $program->id, 'code' => $program->code], actor: $actor);

            return $program;
        });
    }

    /** Copies a national program with its modules and lessons into the office. */
    public function copyNational(GuidanceProgram $national, string $code, User $actor): GuidanceProgram
    {
        $this->authorize($actor);

        if (! $national->isNational()) {
            throw GuidanceRuleViolation::programUnavailable();
        }

        return DB::transaction(function () use ($national, $code, $actor): GuidanceProgram {
            $copy = $national->replicate(['national_code_key']);
            $copy->tenant_id = $this->tenantContext->requireId();
            $copy->code = $code;
            $copy->based_on_program_id = $national->id;
            $copy->save();

            foreach ($national->modules()->with('lessons')->get() as $module) {
                $moduleCopy = $module->replicate();
                $moduleCopy->program_id = $copy->id;
                $moduleCopy->save();

                foreach ($module->lessons as $lesson) {
                    $lessonCopy = $lesson->replicate();
                    $lessonCopy->module_id = $moduleCopy->id;
                    $lessonCopy->save();
                }
            }

            $this->audit->record('guidance.program_copied', newValues: ['program_id' => $copy->id, 'from_program_id' => $national->id], actor: $actor);

            return $copy;
        });
    }

    /**
     * Saves a module of an office program with its lessons. Lessons not in
     * $lessons are removed, unless progress was recorded for them.
     *
     * @param  array<string, mixed>  $data  title, description, sort_order, weight, required
     * @param  list<array{id?: int|null, title: string, description?: string|null, learning_objectives?: string|null, sort_order?: int|null}>  $lessons
     */
    public function saveModule(GuidanceProgram $program, ?GuidanceModule $module, array $data, array $lessons, User $actor): GuidanceModule
    {
        $this->authorize($actor);
        $this->ensureOwn($program);

        return DB::transaction(function () use ($program, $module, $data, $lessons): GuidanceModule {
            $module ??= new GuidanceModule;
            $module->program_id = $program->id;

            foreach (['title', 'description', 'sort_order', 'weight', 'required'] as $field) {
                if (array_key_exists($field, $data)) {
                    $module->setAttribute($field, $data[$field]);
                }
            }

            $module->save();

            $keep = [];

            foreach ($lessons as $position => $row) {
                $lesson = isset($row['id']) ? GuidanceLesson::query()->where('module_id', $module->id)->find($row['id']) : null;
                $lesson ??= new GuidanceLesson;
                $lesson->module_id = $module->id;
                $lesson->title = $row['title'];
                $lesson->description = $row['description'] ?? null;
                $lesson->learning_objectives = $row['learning_objectives'] ?? null;
                $lesson->sort_order = (int) ($row['sort_order'] ?? $position);
                $lesson->save();
                $keep[] = $lesson->id;
            }

            $removed = GuidanceLesson::query()->where('module_id', $module->id)->whereNotIn('id', $keep)->pluck('id');

            if ($removed->isNotEmpty() && LearningProgress::query()->withoutGlobalScopes()->whereIn('lesson_id', $removed)->exists()) {
                throw GuidanceRuleViolation::lessonInUse();
            }

            GuidanceLesson::query()->whereKey($removed)->delete();

            return $module;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('guidance.programs.manage')) {
            throw new AuthorizationException;
        }
    }

    private function ensureOwn(?GuidanceProgram $program): void
    {
        if ($program !== null && $program->tenant_id !== $this->tenantContext->requireId()) {
            throw GuidanceRuleViolation::notOwnProgram();
        }
    }
}
