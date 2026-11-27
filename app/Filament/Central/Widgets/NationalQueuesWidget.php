<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Domain\Privileged\Enums\PrivilegedStatus;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Registration\Models\ShahadahVoidRequest;
use App\Filament\Central\Resources\PrivilegedAccess\PrivilegedAccessResource;
use App\Filament\Central\Resources\ShahadahVoidRequests\ShahadahVoidRequestResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Requests waiting for a national decision, each shown only to those who may
 * decide it, with a link to its list.
 */
final class NationalQueuesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return self::decidesVoidRequests() || self::approvesPrivilegedAccess();
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.national.queues_heading');
    }

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $stats = [];

        if (self::decidesVoidRequests()) {
            $pending = ShahadahVoidRequest::query()->where('status', VoidRequestStatus::Pending)->count();
            $stats[] = Stat::make(__('dashboard.national.void_requests'), number_format($pending))
                ->description(__('dashboard.national.void_requests_help'))
                ->descriptionIcon(Heroicon::OutlinedNoSymbol)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url(ShahadahVoidRequestResource::getUrl());
        }

        if (self::approvesPrivilegedAccess()) {
            $pending = PrivilegedAccessRequest::query()->where('status', PrivilegedStatus::Pending)->count();
            $stats[] = Stat::make(__('dashboard.national.privileged_requests'), number_format($pending))
                ->description(__('dashboard.national.privileged_requests_help'))
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url(PrivilegedAccessResource::getUrl());
        }

        return $stats;
    }

    private static function decidesVoidRequests(): bool
    {
        return auth()->user()?->can('viewAny', ShahadahVoidRequest::class) ?? false;
    }

    private static function approvesPrivilegedAccess(): bool
    {
        return auth()->user()?->hasPermission('privileged.approve') ?? false;
    }
}
