<?php

declare(strict_types=1);

namespace App\Domain\Dai\Services;

use App\Domain\Dai\Data\DaiData;
use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Exceptions\DaiRuleViolation;
use App\Domain\Dai\Models\Dai;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\UserTenantMembership;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Rules shared by CreateDai and UpdateDai. Every lookup goes through the
 * tenant-scoped Dai model, so a supervisor from another office is simply not found.
 */
final readonly class DaiRules
{
    public function __construct(private TenantContext $tenantContext) {}

    public function check(DaiData $data, ?Dai $dai = null): void
    {
        if ($data->userId !== null) {
            $this->checkUser($data->userId);
        }

        if ($data->supervisorDaiId !== null) {
            $this->checkSupervisor($data->supervisorDaiId, $dai);
        }
    }

    /**
     * Fills the profile attributes (not status) from the data.
     */
    public function fill(Dai $dai, DaiData $data): void
    {
        $dai->employee_number = $data->employeeNumber;
        $dai->full_name = $data->fullName;
        $dai->full_name_ar = $data->fullNameAr;
        $dai->gender = $data->gender;
        $dai->employment_status = $data->employmentStatus;
        $dai->nationality_id = $data->nationalityId;
        $dai->phone_encrypted = $data->phone;
        $dai->email = $data->email;
        $dai->education = $data->education;
        $dai->specialties = $data->specialties;
        $dai->supervisor_dai_id = $data->supervisorDaiId;
        $dai->joined_at = $data->joinedAt;
        $dai->user_id = $data->userId;
    }

    /**
     * Saves, turning unique-key violations into rule violations on their field.
     */
    public function save(Dai $dai): void
    {
        try {
            $dai->save();
        } catch (UniqueConstraintViolationException $exception) {
            // The SQL text always mentions both columns; the violated key names the rule.
            throw str_contains($exception->getMessage(), 'dais_tenant_id_user_id_unique')
                ? DaiRuleViolation::userAlreadyLinked()
                : DaiRuleViolation::duplicateEmployeeNumber();
        }
    }

    /**
     * @return array<int, string> language_id => proficiency, for audit
     */
    public function syncLanguages(Dai $dai, DaiData $data): array
    {
        $dai->languages()->sync(array_map(
            static fn ($proficiency): array => ['proficiency' => $proficiency->value],
            $data->languages,
        ));

        return array_map(static fn ($proficiency): string => $proficiency->value, $data->languages);
    }

    private function checkUser(int $userId): void
    {
        $isMember = UserTenantMembership::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $this->tenantContext->requireId())
            ->where('status', MembershipStatus::Active)
            ->exists();

        if (! $isMember) {
            throw DaiRuleViolation::userNotMember();
        }
    }

    /**
     * The supervisor must be another active da'i of this office, and the chain
     * of supervisors above it must not lead back to the da'i being saved.
     */
    private function checkSupervisor(int $supervisorId, ?Dai $dai): void
    {
        if ($dai !== null && $supervisorId === $dai->id) {
            throw DaiRuleViolation::supervisorIsSelf();
        }

        $supervisor = Dai::query()->find($supervisorId);

        if (! $supervisor instanceof Dai || $supervisor->status !== DaiStatus::Active) {
            throw DaiRuleViolation::supervisorUnavailable();
        }

        if ($dai === null) {
            return;
        }

        $seen = [];
        $current = $supervisor;

        while ($current instanceof Model && $current->supervisor_dai_id !== null) {
            if ($current->supervisor_dai_id === $dai->id) {
                throw DaiRuleViolation::supervisorCycle();
            }

            if (isset($seen[$current->id])) {
                break;
            }

            $seen[$current->id] = true;
            $current = Dai::query()->find($current->supervisor_dai_id);
        }
    }
}
