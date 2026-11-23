<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Guidance\Events\EnrollmentCompleted;
use App\Domain\International\Events\ConsentWithdrawn;
use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Registration\Events\ShahadahRecorded;
use App\Domain\Registration\Events\ShahadahVerified;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Events\TransferApproved;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Domain\Transfers\Events\TransferRequested;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 12 §12.6 notifications to office staff, queued on `critical` after
 * commit with the office ids carried by each event; every alert
 * is generic text with a link inside the recipient's own office.
 */
final class NotifyOfficeStaff implements ShouldQueue
{
    public string $queue = 'critical';

    public function __construct(private readonly OfficeRecipients $recipients, private readonly OfficeLinks $links, private readonly TenantContext $tenantContext) {}

    public function handle(ShahadahRecorded|ShahadahVerified|TransferRequested|TransferApproved|TransferCompleted|EnrollmentCompleted|ConsentWithdrawn $event): void
    {
        match (true) {
            $event instanceof ShahadahRecorded => $this->send($event->tenantId, 'shahadah.verify', 'shahadah_recorded', fn (Tenant $tenant): string => $this->links->shahadahRegistrations($tenant)),
            $event instanceof ShahadahVerified => $this->send($event->tenantId, 'certificates.request', 'shahadah_verified', fn (Tenant $tenant): string => $this->links->certificates($tenant)),
            $event instanceof EnrollmentCompleted => $this->send($event->tenantId, 'certificates.request', 'enrollment_completed', fn (Tenant $tenant): string => $this->links->certificates($tenant)),
            $event instanceof TransferRequested => $this->send($event->fromTenantId, 'converts.review_transfer', 'transfer_requested', fn (Tenant $tenant): string => $this->links->transfers($tenant)),
            $event instanceof TransferApproved => $this->send($event->toTenantId, 'converts.transfer', 'transfer_approved', fn (Tenant $tenant): string => $this->links->transfers($tenant)),
            $event instanceof TransferCompleted => $this->transferCompleted($event),
            $event instanceof ConsentWithdrawn => $this->consentWithdrawn($event),
        };
    }

    private function transferCompleted(TransferCompleted $event): void
    {
        $this->send($event->fromTenantId, 'converts.review_transfer', 'transfer_completed_out', fn (Tenant $tenant): string => $this->links->transfers($tenant));
        $this->send($event->toTenantId, 'converts.transfer', 'transfer_completed_in', fn (Tenant $tenant): string => $this->links->transfers($tenant));

        $daiId = $this->tenantContext->run($event->toTenantId, fn (): mixed => DB::table('converts')->where('id', $event->convertId)->value('primary_dai_id'));
        $dai = $daiId === null ? null : $this->recipients->daiUser($event->toTenantId, (int) $daiId);

        if ($dai !== null) {
            $this->notify(collect([$dai]), $event->toTenantId, 'transfer_new_convert', $this->convertUrl($event->toTenantId, $event->convertId));
        }
    }

    private function consentWithdrawn(ConsentWithdrawn $event): void
    {
        $daiId = DB::table('converts')->where('id', $event->convertId)->value('primary_dai_id');
        $dai = $daiId === null ? null : $this->recipients->daiUser($event->tenantId, (int) $daiId);

        if ($dai !== null) {
            $this->notify(collect([$dai]), $event->tenantId, 'consent_withdrawn', $this->convertUrl($event->tenantId, $event->convertId), ['count' => $event->referralsClosed]);
        }
    }

    /** @param  \Closure(Tenant): string  $link */
    private function send(int $tenantId, string $permission, string $kind, \Closure $link): void
    {
        $tenant = Tenant::query()->find($tenantId);
        $url = $tenant === null ? null : $link($tenant);

        $this->notify($this->recipients->withPermission($tenantId, $permission), $tenantId, $kind, $url);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, string|int>  $parameters
     */
    private function notify(Collection $users, int $tenantId, string $kind, ?string $url, array $parameters = []): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new OfficeAlert($kind, $tenantId, $parameters, $url));
        }
    }

    private function convertUrl(int $tenantId, int $convertId): ?string
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant === null ? null : $this->links->convert($tenant, $convertId);
    }
}
