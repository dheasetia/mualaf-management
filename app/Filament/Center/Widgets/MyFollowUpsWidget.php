<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Filament\Center\Resources\FollowUpSchedules\FollowUpScheduleResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The signed-in da'i's own scheduled follow-ups that are overdue or due today.
 * Names follow ConvertAccess, as on the follow-up schedule page.
 */
final class MyFollowUpsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && FollowUpScheduleResource::canViewAny()
            && app(DaiDirectory::class)->daiIdForUser($user) !== null;
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $user = auth()->user();
        $daiId = $user instanceof User ? app(DaiDirectory::class)->daiIdForUser($user) : null;
        $endOfToday = CarbonImmutable::now($timezone)->endOfDay()->utc();

        return $table
            ->heading(__('dashboard.my_follow_ups.heading'))
            ->description(__('dashboard.my_follow_ups.description'))
            ->query(fn () => ConvertFollowUpSchedule::query()
                ->where('dai_id', $daiId ?? 0)
                ->where('status', ScheduleStatus::Scheduled)
                ->where('scheduled_at', '<=', $endOfToday))
            ->defaultSort('scheduled_at')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('dashboard.my_follow_ups.empty'))
            ->recordUrl(fn (): string => FollowUpScheduleResource::getUrl())
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label(__('dashboard.my_follow_ups.time'))
                    ->dateTime('Y-m-d H:i', $timezone)
                    ->color(fn (ConvertFollowUpSchedule $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('convert')
                    ->label(__('followup.fields.convert'))
                    ->state(fn (ConvertFollowUpSchedule $record): string => ($viewer = auth()->user()) instanceof User && ($convert = Convert::query()->find($record->convert_id)) instanceof Convert
                        ? app(ConvertAccess::class)->displayName($viewer, $convert) : '—'),
                TextColumn::make('follow_up_type')
                    ->label(__('followup.fields.type'))
                    ->formatStateUsing(fn (string $state): string => (string) (__('followup.types.'.$state) === 'followup.types.'.$state ? __('followup.triggers.'.$state) : __('followup.types.'.$state))),
                TextColumn::make('priority')
                    ->label(__('followup.fields.priority'))
                    ->badge()
                    ->formatStateUsing(fn (Priority $state): string => $state->label())
                    ->color(fn (Priority $state): string => $state === Priority::High ? 'danger' : 'gray'),
            ]);
    }
}
