<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Listeners;

use App\Domain\FollowUp\Enums\TriggerEvent;
use App\Domain\FollowUp\Events\ConvertReturnedHome;
use App\Domain\FollowUp\Services\FollowUpScheduler;
use App\Domain\Guidance\Events\EnrollmentCompleted;
use App\Domain\Registration\Events\ShahadahVerified;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Follow-up schedules from events (10 §10.4), in the office now
 * responsible for the convert.
 */
final readonly class ScheduleFollowUps
{
    public function __construct(private FollowUpScheduler $scheduler, private TenantContext $tenantContext) {}

    public function handle(ShahadahVerified|EnrollmentCompleted|TransferCompleted|ConvertReturnedHome $event): void
    {
        [$trigger, $tenantId] = match (true) {
            $event instanceof ShahadahVerified => [TriggerEvent::Shahadah, $event->tenantId],
            $event instanceof EnrollmentCompleted => [TriggerEvent::GuidanceCompleted, $event->tenantId],
            $event instanceof TransferCompleted => [TriggerEvent::TransferCompleted, $event->toTenantId],
            $event instanceof ConvertReturnedHome => [TriggerEvent::ReturnedHome, $event->tenantId],
        };

        $this->tenantContext->run($tenantId, fn (): int => $this->scheduler->schedule($trigger, $event->convertId, CarbonImmutable::now()));
    }
}
