<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Pilgrimage\Enums\ProgramStatus;
use App\Domain\Pilgrimage\Enums\ProgramType;
use App\Domain\Pilgrimage\Enums\RegistrationStatus;
use App\Domain\Pilgrimage\Enums\RequirementStatus;
use App\Domain\Pilgrimage\Exceptions\PilgrimageRuleViolation;
use App\Domain\Pilgrimage\Models\PilgrimageGroup;
use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Pilgrimage\Models\PilgrimageRegistrationRequirement;
use App\Domain\Pilgrimage\Models\PilgrimageRequirement;
use App\Domain\Pilgrimage\Services\PilgrimageEvents;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Programs, their requirements and groups (pilgrimage.programs.manage).
 * A program is edited while a draft or open; capacity never drops below
 * the seats already taken. Starting a program marks confirmed pilgrims as
 * departed; completing it marks departed pilgrims as completed.
 */
final readonly class ManagePrograms
{
    public function __construct(private PilgrimageEvents $events, private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function save(?PilgrimageProgram $program, array $data, User $actor): PilgrimageProgram
    {
        $this->authorize($actor);

        if ($program !== null && ! in_array($program->status, [ProgramStatus::Draft, ProgramStatus::Open], true)) {
            throw PilgrimageRuleViolation::notEditable();
        }

        $starts = CarbonImmutable::parse((string) $data['starts_on']);
        $ends = CarbonImmutable::parse((string) $data['ends_on']);
        $deadline = CarbonImmutable::parse((string) $data['registration_deadline']);

        if ($ends->lessThan($starts) || $deadline->greaterThan($starts)) {
            throw PilgrimageRuleViolation::datesInvalid();
        }

        $capacity = max(1, (int) $data['capacity']);

        if ($program !== null && $capacity < $this->seatsTaken($program)) {
            throw PilgrimageRuleViolation::capacityBelowSeats();
        }

        $program ??= new PilgrimageProgram;
        $program->program_type = ProgramType::from((string) $data['program_type']);
        $program->name = (string) $data['name'];
        $program->season_year = (int) $data['season_year'];
        $program->starts_on = $starts;
        $program->ends_on = $ends;
        $program->registration_deadline = $deadline;
        $program->capacity = $capacity;
        $program->eligibility_rules = [
            'min_months_since_shahadah' => blank($data['min_months_since_shahadah'] ?? null) ? null : max(0, (int) $data['min_months_since_shahadah']),
            'require_completed_guidance' => (bool) ($data['require_completed_guidance'] ?? false),
        ];

        if (! $program->exists) {
            $program->status = ProgramStatus::Draft;
            $program->created_by = $actor->id;
        }

        $program->save();
        $this->audit->recordChanges('pilgrimage_program.saved', $program, actor: $actor);

        return $program;
    }

    public function changeStatus(PilgrimageProgram $program, ProgramStatus $target, User $actor): PilgrimageProgram
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($program, $target, $actor): PilgrimageProgram {
            $locked = PilgrimageProgram::query()->lockForUpdate()->findOrFail($program->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw PilgrimageRuleViolation::invalidTransition($from->label(), $target->label());
            }

            $locked->status = $target;
            $locked->save();

            $follow = match ($target) {
                ProgramStatus::InProgress => [RegistrationStatus::Confirmed, RegistrationStatus::Departed],
                ProgramStatus::Completed => [RegistrationStatus::Departed, RegistrationStatus::Completed],
                default => null,
            };

            if ($follow !== null) {
                $locked->registrations()->where('status', $follow[0])->update(['status' => $follow[1], 'updated_at' => now()]);
            }

            $this->events->record($locked->id, null, $actor, 'program_status_changed', ['from' => $from->value, 'to' => $target->value]);

            return $locked;
        });
    }

    public function addRequirement(PilgrimageProgram $program, string $code, string $description, bool $required, User $actor): PilgrimageRequirement
    {
        $this->authorize($actor);

        if (! in_array($program->status, [ProgramStatus::Draft, ProgramStatus::Open], true)) {
            throw PilgrimageRuleViolation::notEditable();
        }

        try {
            return DB::transaction(function () use ($program, $code, $description, $required): PilgrimageRequirement {
                $requirement = new PilgrimageRequirement;
                $requirement->program_id = $program->id;
                $requirement->code = trim($code);
                $requirement->description = trim($description);
                $requirement->required = $required;
                $requirement->save();

                $program->registrations()->whereNotIn('status', [RegistrationStatus::Ineligible, RegistrationStatus::Withdrawn])->pluck('id')
                    ->each(function (int $registrationId) use ($requirement): void {
                        $row = new PilgrimageRegistrationRequirement;
                        $row->registration_id = $registrationId;
                        $row->requirement_id = $requirement->id;
                        $row->status = RequirementStatus::Pending;
                        $row->save();
                    });

                return $requirement;
            });
        } catch (UniqueConstraintViolationException) {
            throw PilgrimageRuleViolation::duplicateRequirement();
        }
    }

    public function createGroup(PilgrimageProgram $program, string $name, User $leader, int $capacity, User $actor): PilgrimageGroup
    {
        $this->authorize($actor);

        if (! $leader->hasPermission('pilgrimage.view')) {
            throw PilgrimageRuleViolation::leaderUnavailable();
        }

        $group = new PilgrimageGroup;
        $group->program_id = $program->id;
        $group->group_name = trim($name);
        $group->leader_user_id = $leader->id;
        $group->capacity = max(1, $capacity);
        $group->save();

        $this->events->record($program->id, null, $actor, 'group_created', ['group_id' => $group->id]);

        return $group;
    }

    /** Places an approved or confirmed pilgrim of the same program in a group with room. */
    public function addToGroup(PilgrimageGroup $group, PilgrimageRegistration $registration, User $actor): void
    {
        $this->authorize($actor);

        DB::transaction(function () use ($group, $registration, $actor): void {
            $locked = PilgrimageGroup::query()->lockForUpdate()->findOrFail($group->id);

            if ($registration->program_id !== $locked->program_id || ! in_array($registration->status, [RegistrationStatus::Approved, RegistrationStatus::Confirmed], true)) {
                throw PilgrimageRuleViolation::notInProgram();
            }

            if ($locked->members()->count() >= $locked->capacity) {
                throw PilgrimageRuleViolation::groupFull();
            }

            DB::table('pilgrimage_group_members')->where('pilgrimage_registration_id', $registration->id)->delete();
            $locked->members()->attach($registration->id);

            $this->events->record($locked->program_id, $registration->id, $actor, 'grouped', ['group_id' => $locked->id]);
        });
    }

    public function seatsTaken(PilgrimageProgram $program): int
    {
        return $program->registrations()
            ->whereIn('status', array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $status): bool => $status->takesSeat()))
            ->count();
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('pilgrimage.programs.manage')) {
            throw new AuthorizationException;
        }
    }
}
