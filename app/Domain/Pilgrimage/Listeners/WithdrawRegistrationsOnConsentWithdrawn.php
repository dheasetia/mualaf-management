<?php

declare(strict_types=1);

namespace App\Domain\Pilgrimage\Listeners;

use App\Domain\International\Events\ConsentWithdrawn;
use App\Domain\Pilgrimage\Actions\RegistrationWorkflow;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * 10 §10.8: registrations resting on a withdrawn pilgrimage consent end
 * before departure, in the office that recorded the consent.
 */
final readonly class WithdrawRegistrationsOnConsentWithdrawn
{
    public function __construct(private RegistrationWorkflow $registrations, private TenantContext $tenantContext) {}

    public function handle(ConsentWithdrawn $event): void
    {
        $actor = $event->actorId === null ? null : User::query()->find($event->actorId);

        if ($actor === null) {
            return;
        }

        $this->tenantContext->run($event->tenantId, fn (): int => $this->registrations->withdrawForConsent($event->consentId, $actor));
    }
}
