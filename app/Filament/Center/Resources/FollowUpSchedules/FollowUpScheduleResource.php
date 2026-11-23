<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\FollowUpSchedules;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\FollowUp\Actions\LongTermFollowUps;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Filament\Center\Resources\Converts\RelationManagers\LongTermFollowUpsRelationManager;
use App\Filament\Center\Resources\FollowUpSchedules\Pages\ListFollowUpSchedules;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Follow-up schedule (10 §10.4): a da'i sees their own; holders of
 * converts.view (Head of Dawah) see the office. Overdue first.
 */
final class FollowUpScheduleResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = ConvertFollowUpSchedule::class;

    protected static ?string $slug = 'follow-up-schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('followup.schedules.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('followup.schedules.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('followups.view') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $user->hasPermission('converts.view') ? $query : $query->where('dai_id', app(DaiDirectory::class)->daiIdForUser($user) ?? 0);
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $followUps = fn (): LongTermFollowUps => app(LongTermFollowUps::class);
        $open = fn (ConvertFollowUpSchedule $record): bool => $record->status === ScheduleStatus::Scheduled
            && (($user = auth()->user()) instanceof User) && ($convert = Convert::query()->find($record->convert_id)) instanceof Convert && $user->can('recordFollowUp', $convert);

        return $table
            ->defaultSort('scheduled_at')
            ->columns([
                TextColumn::make('scheduled_at')->label(__('followup.fields.scheduled_at'))->dateTime('Y-m-d H:i', $timezone)->sortable()
                    ->color(fn (ConvertFollowUpSchedule $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('convert')->label(__('followup.fields.convert'))
                    ->state(fn (ConvertFollowUpSchedule $record): string => ($user = auth()->user()) instanceof User && ($convert = Convert::query()->find($record->convert_id)) instanceof Convert
                        ? app(ConvertAccess::class)->displayName($user, $convert) : '—'),
                TextColumn::make('follow_up_type')->label(__('followup.fields.type'))
                    ->formatStateUsing(fn (string $state): string => (string) (__('followup.types.'.$state) === 'followup.types.'.$state ? __('followup.triggers.'.$state) : __('followup.types.'.$state))),
                TextColumn::make('priority')->label(__('followup.fields.priority'))->badge()->formatStateUsing(fn (Priority $state): string => $state->label())
                    ->color(fn (Priority $state): string => $state === Priority::High ? 'danger' : 'gray'),
                TextColumn::make('dai')->label(__('followup.fields.dai'))
                    ->state(fn (ConvertFollowUpSchedule $record): string => app(DaiDirectory::class)->names([$record->dai_id])[$record->dai_id] ?? '—'),
                TextColumn::make('status')->label(__('followup.fields.status'))->badge()->formatStateUsing(fn (ScheduleStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('followup.fields.status'))->default(ScheduleStatus::Scheduled->value)
                    ->options(collect(ScheduleStatus::cases())->mapWithKeys(fn (ScheduleStatus $status): array => [$status->value => $status->label()])->all()),
                Filter::make('overdue')->label(__('followup.fields.overdue'))->query(fn (Builder $query): Builder => $query->where('scheduled_at', '<', CarbonImmutable::now())),
            ])
            ->recordActions([
                Action::make('record')->label(__('followup.actions.record'))->color('primary')->visible($open)
                    ->schema(LongTermFollowUpsRelationManager::contactForm())
                    ->action(self::domainAction(fn (ConvertFollowUpSchedule $record, array $data, User $user) => $followUps()
                        ->record(Convert::query()->findOrFail($record->convert_id), $data, $record, $user), 'followup.notifications.saved')),
                Action::make('reschedule')->label(__('followup.actions.reschedule'))->color('gray')->visible($open)
                    ->schema([DateTimePicker::make('scheduled_at')->label(__('followup.fields.scheduled_at'))->required()->seconds(false)])
                    ->action(self::domainAction(fn (ConvertFollowUpSchedule $record, array $data, User $user) => $followUps()
                        ->reschedule($record, CarbonImmutable::parse((string) $data['scheduled_at'], $timezone), $user), 'followup.notifications.saved')),
                Action::make('miss')->label(__('followup.actions.miss'))->color('warning')->requiresConfirmation()->visible($open)
                    ->action(self::domainAction(fn (ConvertFollowUpSchedule $record, array $data, User $user) => $followUps()->miss($record, $user), 'followup.notifications.saved')),
                Action::make('cancel')->label(__('followup.actions.cancel'))->color('danger')->visible($open)
                    ->schema([Textarea::make('reason')->label(__('followup.fields.reason'))->required()->maxLength(500)])
                    ->action(self::domainAction(fn (ConvertFollowUpSchedule $record, array $data, User $user) => $followUps()->cancel($record, $data['reason'] ?? null, $user), 'followup.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFollowUpSchedules::route('/')];
    }
}
