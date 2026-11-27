<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Dai\Enums\DaiStatus;
use App\Domain\Dai\Models\Dai;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Guidance\Enums\ClassStatus;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Pilgrimage\Enums\RegistrationStatus as PilgrimageStatus;
use App\Domain\Pilgrimage\Models\PilgrimageRegistration;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Models\TransportRequest;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Live counts for work the daily aggregates do not cover. Every model is
 * limited to the current office by its tenant (or transfer party) scope;
 * only counts are shown.
 */
final class OperationsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('reports.view_office') ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.operations.heading');
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $now = CarbonImmutable::now();

        return [
            Stat::make(__('dashboard.operations.dais_active'), Dai::query()->where('status', DaiStatus::Active)->count())
                ->descriptionIcon(Heroicon::OutlinedUserCircle)
                ->description(__('dashboard.operations.dais_active_help')),
            Stat::make(__('dashboard.operations.classes_running'), GuidanceClass::query()->whereIn('status', [ClassStatus::Open, ClassStatus::InProgress])->count())
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->description(__('dashboard.operations.classes_running_help')),
            Stat::make(__('dashboard.operations.exams_upcoming'), Exam::query()->where('status', ExamStatus::Scheduled)->whereBetween('scheduled_at', [$now, $now->addDays(30)])->count())
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->description(__('dashboard.operations.exams_upcoming_help')),
            Stat::make(__('dashboard.operations.transfers_open'), ConvertTransferRequest::query()->whereIn('status', [TransferStatus::Pending, TransferStatus::UnderReview, TransferStatus::Approved, TransferStatus::Accepted, TransferStatus::Escalated])->count())
                ->descriptionIcon(Heroicon::OutlinedArrowsRightLeft)
                ->description(__('dashboard.operations.transfers_open_help')),
            Stat::make(__('dashboard.operations.pickups_open'), TransportRequest::query()->whereIn('status', [PickupStatus::Requested, PickupStatus::Assigned])->count())
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->description(__('dashboard.operations.pickups_open_help')),
            Stat::make(__('dashboard.operations.pilgrims_open'), PilgrimageRegistration::query()->whereIn('status', [
                PilgrimageStatus::Applied, PilgrimageStatus::EligibilityChecked, PilgrimageStatus::Eligible,
                PilgrimageStatus::Approved, PilgrimageStatus::Waitlisted, PilgrimageStatus::Confirmed,
            ])->count())
                ->descriptionIcon(Heroicon::OutlinedGlobeAsiaAustralia)
                ->description(__('dashboard.operations.pilgrims_open_help')),
        ];
    }
}
