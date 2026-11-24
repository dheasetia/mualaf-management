<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Domain\Pilgrimage\Services\ProgramDirectory;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Numbering\NumberAllocator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and edits plans (dawah.plans.create): a da'i proposes, names the
 * responsible da'i, and edits only while the plan is a draft or returned
 * for revision. Numbers follow {TENANT}-PLAN-{YYYY}-{0001}.
 */
final readonly class ManagePlans
{
    private const array FIELDS = [
        'title', 'objective', 'description', 'target_audience', 'expected_participants', 'start_time', 'end_time',
        'city_id', 'location', 'latitude', 'longitude', 'google_maps_url', 'transport_required', 'material_required', 'equipment_required',
        'pilgrimage_program_id',
    ];

    public function __construct(
        private DawahActors $actors,
        private DaiDirectory $dais,
        private NumberAllocator $numbers,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
        private ProgramDirectory $programs,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function save(?DawahActivityPlan $plan, array $data, User $actor): DawahActivityPlan
    {
        if (! $actor->hasPermission('dawah.plans.create')) {
            throw new AuthorizationException;
        }

        $daiId = $this->actors->daiId($actor) ?? throw DawahRuleViolation::noDaiProfile();

        if ($plan !== null) {
            if (! $this->actors->isPlanDai($actor, $plan)) {
                throw new AuthorizationException;
            }

            if (! $plan->status->isEditable()) {
                throw DawahRuleViolation::notEditable();
            }
        }

        $this->validate($data);

        return DB::transaction(function () use ($plan, $data, $actor, $daiId): DawahActivityPlan {
            $plan ??= $this->newPlan($daiId, $actor);

            foreach (self::FIELDS as $field) {
                $plan->setAttribute($field, blank($data[$field] ?? null) && ! is_bool($data[$field] ?? null) ? null : $data[$field]);
            }

            $plan->transport_required = (bool) ($data['transport_required'] ?? false);
            $plan->material_required = (bool) ($data['material_required'] ?? false);
            $plan->activity_type_id = (int) $data['activity_type_id'];
            $plan->responsible_dai_id = (int) ($data['responsible_dai_id'] ?? $daiId);
            $plan->planned_date = CarbonImmutable::parse((string) $data['planned_date']);
            $isNew = ! $plan->exists;
            $plan->save();

            $isNew
                ? $this->audit->record('dawah_plan.created', $plan, newValues: ['plan_number' => $plan->plan_number], actor: $actor)
                : $this->audit->recordChanges('dawah_plan.updated', $plan, actor: $actor);

            return $plan;
        });
    }

    private function newPlan(int $daiId, User $actor): DawahActivityPlan
    {
        $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
        $year = CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->format('Y');

        $plan = new DawahActivityPlan;
        $plan->plan_number = sprintf('%s-PLAN-%s-%04d', $tenant->code, $year, $this->numbers->next($tenant->id, 'plan', $year));
        $plan->proposed_by_dai_id = $daiId;
        $plan->status = PlanStatus::Draft;
        $plan->created_by = $actor->id;

        return $plan;
    }

    /** @param  array<string, mixed>  $data */
    private function validate(array $data): void
    {
        if (! DaiActivityType::query()->whereKey((int) ($data['activity_type_id'] ?? 0))->where('active', true)->exists()) {
            throw DawahRuleViolation::activityTypeUnavailable();
        }

        $program = $data['pilgrimage_program_id'] ?? null;

        if (! blank($program) && ! array_key_exists((int) $program, $this->programs->linkableOptions())) {
            throw DawahRuleViolation::programUnavailable();
        }

        $responsible = $data['responsible_dai_id'] ?? null;

        if (! blank($responsible) && ! array_key_exists((int) $responsible, $this->dais->activeOptions())) {
            throw DawahRuleViolation::daiUnavailable();
        }

        $today = CarbonImmutable::now()->setTimezone((string) config('app.display_timezone'))->toDateString();

        if ((string) ($data['planned_date'] ?? '') < $today) {
            throw DawahRuleViolation::dateInPast();
        }

        if (! blank($data['start_time'] ?? null) && ! blank($data['end_time'] ?? null) && (string) $data['end_time'] <= (string) $data['start_time']) {
            throw DawahRuleViolation::endBeforeStart();
        }
    }
}
