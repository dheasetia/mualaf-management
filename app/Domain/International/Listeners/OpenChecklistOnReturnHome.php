<?php

declare(strict_types=1);

namespace App\Domain\International\Listeners;

use App\Domain\Converts\Models\Convert;
use App\Domain\FollowUp\Events\ConvertReturnedHome;
use App\Domain\International\Actions\Checklists;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * PRD §33: a convert returning home gets a pre-departure checklist, unless
 * one is already open.
 */
final readonly class OpenChecklistOnReturnHome
{
    public function __construct(private Checklists $checklists, private TenantContext $tenantContext) {}

    public function handle(ConvertReturnedHome $event): void
    {
        $this->tenantContext->run($event->tenantId, function () use ($event): void {
            $convert = Convert::query()->find($event->convertId);
            $actor = User::query()->find($event->actorId);

            if ($convert === null || $actor === null || PreDepartureChecklist::query()->where('convert_id', $convert->id)->where('status', ChecklistStatus::Open)->exists()) {
                return;
            }

            $this->checklists->open($convert, $event->countryId, null, $actor, authorize: false);
        });
    }
}
