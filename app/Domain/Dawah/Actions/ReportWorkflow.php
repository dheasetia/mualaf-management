<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Dawah\Enums\AttendanceStatus;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Enums\ReportStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Models\DawahActivityReport;
use App\Domain\Dawah\Services\DawahActors;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Activity report (08 §8.6): the responsible da'i writes it once the plan is
 * completed and submits it (dawah.reports.submit), moving the plan to
 * report_submitted; Head of Dawah verifies it (dawah.reports.verify) or
 * returns it with a note, which reopens it for editing.
 */
final readonly class ReportWorkflow
{
    private const array FIELDS = [
        'summary', 'achievements', 'challenges', 'follow_up_actions', 'material_usage_summary', 'transport_summary',
        'actual_start_at', 'actual_end_at',
    ];

    public function __construct(private DawahActors $actors, private PlanWorkflow $plans) {}

    /** @param  array<string, mixed>  $data */
    public function save(DawahActivityPlan $plan, array $data, User $actor): DawahActivityReport
    {
        $this->authorizeWriter($plan, $actor);
        $plan = DawahActivityPlan::query()->findOrFail($plan->id);

        if ($plan->status !== PlanStatus::Completed) {
            throw DawahRuleViolation::notEditable();
        }

        $report = $plan->report()->first() ?? new DawahActivityReport;

        if ($report->exists && ! $report->status->isEditable()) {
            throw DawahRuleViolation::notEditable();
        }

        if (blank($data['summary'] ?? null)) {
            throw DawahRuleViolation::reportMissing();
        }

        $report->activity_plan_id = $plan->id;
        $report->dai_id = $plan->responsible_dai_id;

        foreach (self::FIELDS as $field) {
            $report->setAttribute($field, blank($data[$field] ?? null) ? null : $data[$field]);
        }

        $report->actual_participants = isset($data['actual_participants']) && $data['actual_participants'] !== ''
            ? (int) $data['actual_participants']
            : $plan->participants()->where('attendance_status', AttendanceStatus::Attended)->count();
        $report->new_prospects_count = (int) ($data['new_prospects_count'] ?? 0);
        $report->shahadah_count = (int) ($data['shahadah_count'] ?? 0);
        $report->status = $report->exists ? $report->status : ReportStatus::Draft;
        $report->save();

        return $report;
    }

    public function submit(DawahActivityPlan $plan, User $actor): DawahActivityReport
    {
        $this->authorizeWriter($plan, $actor);
        $report = $plan->report()->first() ?? throw DawahRuleViolation::reportMissing();

        return DB::transaction(function () use ($plan, $report, $actor): DawahActivityReport {
            $this->plans->move($plan, PlanStatus::ReportSubmitted, $actor);

            $report->status = ReportStatus::Submitted;
            $report->submitted_at = CarbonImmutable::now();
            $report->actual_budget = DawahActivityPlan::query()->whereKey($plan->id)->value('actual_budget');
            $report->return_note = null;
            $report->save();

            return $report;
        });
    }

    public function verify(DawahActivityPlan $plan, User $actor): DawahActivityReport
    {
        $this->authorizeVerifier($plan, $actor);
        $report = $plan->report()->firstOrFail();

        return DB::transaction(function () use ($plan, $report, $actor): DawahActivityReport {
            $this->plans->move($plan, PlanStatus::Verified, $actor);

            $report->status = ReportStatus::Verified;
            $report->verified_by = $actor->id;
            $report->verified_at = CarbonImmutable::now();
            $report->save();

            return $report;
        });
    }

    public function return(DawahActivityPlan $plan, User $actor, ?string $note): DawahActivityReport
    {
        $this->authorizeVerifier($plan, $actor);

        if (blank($note)) {
            throw DawahRuleViolation::reasonRequired();
        }

        $report = $plan->report()->firstOrFail();

        return DB::transaction(function () use ($plan, $report, $actor, $note): DawahActivityReport {
            $this->plans->move($plan, PlanStatus::Completed, $actor, details: ['note' => trim((string) $note)]);

            $report->status = ReportStatus::Returned;
            $report->return_note = trim((string) $note);
            $report->save();

            return $report;
        });
    }

    private function authorizeWriter(DawahActivityPlan $plan, User $actor): void
    {
        if (! $actor->hasPermission('dawah.reports.submit') || ! $this->actors->isResponsible($actor, $plan)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeVerifier(DawahActivityPlan $plan, User $actor): void
    {
        if (! $actor->hasPermission('dawah.reports.verify') || $this->actors->isPlanDai($actor, $plan)) {
            throw new AuthorizationException;
        }
    }
}
