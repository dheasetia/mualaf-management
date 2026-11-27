<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ActivityTransport;

use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Transport\Actions\ActivityTransportWorkflow;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\TransportType;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Filament\Center\Resources\ActivityTransport\Pages\ManageActivityTransport;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ActivityTransportRequestResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = ActivityTransportRequest::class;

    protected static ?string $slug = 'activity-transport';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return __('transport.activity.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport.activity.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('transport.navigation_group');
    }

    public static function table(Table $table): Table
    {
        $workflow = fn (): ActivityTransportWorkflow => app(ActivityTransportWorkflow::class);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
        $reason = Textarea::make('reason')->label(__('transport.fields.reason'))->required()->maxLength(1000);

        return $table
            ->defaultSort('departure_at', 'desc')
            ->columns([
                TextColumn::make('plan')->label(__('transport.fields.plan'))
                    ->state(fn (ActivityTransportRequest $record): string => (string) DawahActivityPlan::query()->whereKey($record->activity_plan_id)->value('title')),
                TextColumn::make('transport_type')->label(__('transport.fields.transport_type'))->formatStateUsing(fn (TransportType $state): string => $state->label()),
                TextColumn::make('departure_at')->label(__('transport.fields.departure_at'))->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone'))->sortable(),
                TextColumn::make('passenger_count')->label(__('transport.fields.passenger_count')),
                TextColumn::make('status')->label(__('transport.fields.status'))->badge()->formatStateUsing(fn (ActivityTransportStatus $state): string => $state->label()),
            ])
            ->headerActions([
                Action::make('request')->label(__('transport.actions.request'))->visible(fn (): bool => $can('transport.request'))
                    ->schema([
                        Section::make()->columnSpanFull()->schema([
                            Fieldset::make(__('forms.basic'))->columns(2)->schema([
                                Select::make('activity_plan_id')->label(__('transport.fields.plan'))->required()->searchable()
                                    ->options(fn (): array => DawahActivityPlan::query()->whereIn('status', [PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::SupervisorReview, PlanStatus::NeedsRevision, PlanStatus::Approved, PlanStatus::Scheduled])
                                        ->orderBy('planned_date')->pluck('title', 'id')->all()),
                                Select::make('transport_type')->label(__('transport.fields.transport_type'))->required()->options(collect(TransportType::cases())->mapWithKeys(fn (TransportType $case): array => [$case->value => $case->label()])->all()),
                                TextInput::make('passenger_count')->label(__('transport.fields.passenger_count'))->required()->integer()->minValue(1),
                                TextInput::make('vehicle_count')->label(__('transport.fields.vehicle_count'))->integer()->minValue(1)->default(1),
                            ]),
                            Fieldset::make(__('forms.schedule'))->columns(2)->schema([
                                DateTimePicker::make('departure_at')->label(__('transport.fields.departure_at'))->required()->seconds(false),
                                DateTimePicker::make('return_at')->label(__('transport.fields.return_at'))->seconds(false),
                                Toggle::make('pickup_required')->label(__('transport.fields.pickup_required')),
                            ]),
                            Fieldset::make(__('forms.notes'))->columns(1)->schema([
                                Textarea::make('notes')->label(__('transport.fields.notes'))->maxLength(1000),
                            ]),
                        ]),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => $workflow()->request(
                        DawahActivityPlan::query()->findOrFail((int) $data['activity_plan_id']), $data, $user,
                    ), 'transport.notifications.saved')),
            ])
            ->recordActions([
                Action::make('approve')->label(__('transport.actions.approve'))->color('success')->requiresConfirmation()
                    ->visible(fn (ActivityTransportRequest $record): bool => $record->status === ActivityTransportStatus::Requested && $can('transport.approve') && $record->requested_by !== auth()->id())
                    ->action(self::domainAction(fn (ActivityTransportRequest $record, array $data, User $user) => $workflow()->approve($record, $user), 'transport.notifications.saved')),
                Action::make('reject')->label(__('transport.actions.reject'))->color('danger')->schema([$reason])
                    ->visible(fn (ActivityTransportRequest $record): bool => $record->status === ActivityTransportStatus::Requested && $can('transport.approve') && $record->requested_by !== auth()->id())
                    ->action(self::domainAction(fn (ActivityTransportRequest $record, array $data, User $user) => $workflow()->reject($record, $user, $data['reason'] ?? null), 'transport.notifications.saved')),
                Action::make('complete')->label(__('transport.actions.complete'))->requiresConfirmation()
                    ->visible(fn (ActivityTransportRequest $record): bool => $record->status === ActivityTransportStatus::Scheduled && $can('transport.dispatch'))
                    ->action(self::domainAction(fn (ActivityTransportRequest $record, array $data, User $user) => $workflow()->complete($record, $user), 'transport.notifications.saved')),
                Action::make('cancel')->label(__('transport.actions.cancel'))->color('danger')->schema([$reason])
                    ->visible(fn (ActivityTransportRequest $record): bool => $record->status->canTransitionTo(ActivityTransportStatus::Cancelled) && ($record->requested_by === auth()->id() || $can('transport.approve')))
                    ->action(self::domainAction(fn (ActivityTransportRequest $record, array $data, User $user) => $workflow()->cancel($record, $user, $data['reason'] ?? null), 'transport.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageActivityTransport::route('/')];
    }
}
