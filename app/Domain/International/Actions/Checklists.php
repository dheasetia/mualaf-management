<?php

declare(strict_types=1);

namespace App\Domain\International\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\International\Enums\ChecklistItemCode;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Enums\ItemStatus;
use App\Domain\International\Exceptions\InternationalRuleViolation;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Domain\International\Models\PreDepartureChecklistItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Pre-departure checklist (PRD §33): one open checklist per convert, all
 * items pending at start; completed once no item is pending.
 */
final readonly class Checklists
{
    public function __construct(private AuditLogger $audit) {}

    public function open(Convert $convert, ?int $countryId, ?CarbonImmutable $departure, User $actor, bool $authorize = true): PreDepartureChecklist
    {
        if ($authorize && ! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }

        if (PreDepartureChecklist::query()->where('convert_id', $convert->id)->where('status', ChecklistStatus::Open)->exists()) {
            throw InternationalRuleViolation::checklistOpen();
        }

        return DB::transaction(function () use ($convert, $countryId, $departure, $actor): PreDepartureChecklist {
            $checklist = new PreDepartureChecklist;
            $checklist->convert_id = $convert->id;
            $checklist->primary_dai_id = $convert->primary_dai_id;
            $checklist->destination_country_id = $countryId;
            $checklist->expected_departure_on = $departure;
            $checklist->status = ChecklistStatus::Open;
            $checklist->created_by = $actor->id;
            $checklist->save();

            foreach (ChecklistItemCode::cases() as $code) {
                $item = new PreDepartureChecklistItem;
                $item->checklist_id = $checklist->id;
                $item->item_code = $code;
                $item->status = ItemStatus::Pending;
                $item->save();
            }

            $this->audit->record('checklist.opened', $convert, newValues: ['checklist_id' => $checklist->id], actor: $actor);

            return $checklist;
        });
    }

    public function mark(PreDepartureChecklistItem $item, ItemStatus $status, ?string $notes, User $actor): PreDepartureChecklistItem
    {
        $checklist = $item->checklist()->firstOrFail();
        $this->authorize($checklist, $actor);

        if ($checklist->status !== ChecklistStatus::Open) {
            throw InternationalRuleViolation::invalidTransition($checklist->status->label(), ChecklistStatus::Open->label());
        }

        $item->status = $status;
        $item->notes = blank($notes) ? null : trim((string) $notes);
        $item->done_by = $status === ItemStatus::Pending ? null : $actor->id;
        $item->done_at = $status === ItemStatus::Pending ? null : CarbonImmutable::now();
        $item->save();

        return $item;
    }

    public function complete(PreDepartureChecklist $checklist, User $actor): PreDepartureChecklist
    {
        $this->authorize($checklist, $actor);

        if ($checklist->items()->where('status', ItemStatus::Pending)->exists()) {
            throw InternationalRuleViolation::itemsPending();
        }

        return $this->close($checklist, ChecklistStatus::Completed, $actor);
    }

    public function cancel(PreDepartureChecklist $checklist, User $actor): PreDepartureChecklist
    {
        $this->authorize($checklist, $actor);

        return $this->close($checklist, ChecklistStatus::Cancelled, $actor);
    }

    private function close(PreDepartureChecklist $checklist, ChecklistStatus $target, User $actor): PreDepartureChecklist
    {
        if ($checklist->status !== ChecklistStatus::Open) {
            throw InternationalRuleViolation::invalidTransition($checklist->status->label(), $target->label());
        }

        $checklist->status = $target;
        $checklist->completed_at = $target === ChecklistStatus::Completed ? CarbonImmutable::now() : null;
        $checklist->save();

        $this->audit->record('checklist.'.$target->value, $checklist, actor: $actor);

        return $checklist;
    }

    private function authorize(PreDepartureChecklist $checklist, User $actor): void
    {
        $convert = Convert::query()->find($checklist->convert_id);

        if ($convert === null || ! $actor->can('recordFollowUp', $convert)) {
            throw new AuthorizationException;
        }
    }
}
