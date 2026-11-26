<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Trips;

use App\Domain\Transport\Actions\Dispatch;
use App\Domain\Transport\Enums\ActivityTransportStatus;
use App\Domain\Transport\Enums\DriverStatus;
use App\Domain\Transport\Enums\TripStatus;
use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Models\ActivityTransportRequest;
use App\Domain\Transport\Models\Driver;
use App\Domain\Transport\Models\TransportTrip;
use App\Domain\Transport\Models\Vehicle;
use App\Filament\Center\Resources\Trips\Pages\ManageTrips;
use App\Filament\Center\Resources\Trips\Pages\PickupSheetPage;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TripResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = TransportTrip::class;

    protected static ?string $slug = 'trips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 14;

    public static function getModelLabel(): string
    {
        return __('transport.trips.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport.trips.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('transport.navigation_group');
    }

    public static function table(Table $table): Table
    {
        $can = fn (): bool => auth()->user()?->hasPermission('transport.dispatch') ?? false;
        $dispatch = fn (): Dispatch => app(Dispatch::class);
        $timezone = (string) config('app.display_timezone');

        return $table
            ->defaultSort('departs_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['vehicle', 'driver'])->withCount('pickups'))
            ->columns([
                TextColumn::make('departs_at')->label(__('transport.fields.departs_at'))->dateTime('Y-m-d H:i', timezone: $timezone)->sortable(),
                TextColumn::make('returns_at')->label(__('transport.fields.returns_at'))->dateTime('Y-m-d H:i', timezone: $timezone),
                TextColumn::make('vehicle.plate_number')->label(__('transport.fields.vehicle')),
                TextColumn::make('driver.name')->label(__('transport.fields.driver')),
                TextColumn::make('pickups_count')->label(__('transport.fields.pickups')),
                TextColumn::make('status')->label(__('transport.fields.status'))->badge()->formatStateUsing(fn (TripStatus $state): string => $state->label()),
            ])
            ->headerActions([
                Action::make('newTrip')->label(__('transport.actions.new_trip'))->visible($can)
                    ->schema([
                        Section::make()->columnSpanFull()->schema([
                            Fieldset::make(__('forms.trip'))->columns(2)->schema([
                                Select::make('vehicle_id')->label(__('transport.fields.vehicle'))->required()
                                    ->options(fn (): array => Vehicle::query()->where('status', VehicleStatus::Available)->orderBy('plate_number')->pluck('plate_number', 'id')->all()),
                                Select::make('driver_id')->label(__('transport.fields.driver'))->required()
                                    ->options(fn (): array => Driver::query()->where('status', DriverStatus::Active)->orderBy('name')->pluck('name', 'id')->all()),
                                Select::make('activity_transport_request_id')->label(__('transport.fields.activity_transport'))
                                    ->options(fn (): array => ActivityTransportRequest::query()->whereIn('status', [ActivityTransportStatus::Approved, ActivityTransportStatus::Scheduled])
                                        ->orderBy('departure_at')->get()->mapWithKeys(fn (ActivityTransportRequest $request): array => [$request->id => '#'.$request->id.' · '.$request->departure_at->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i')])->all()),
                            ]),
                            Fieldset::make(__('forms.schedule'))->columns(2)->schema([
                                DateTimePicker::make('departs_at')->label(__('transport.fields.departs_at'))->required()->seconds(false),
                                DateTimePicker::make('returns_at')->label(__('transport.fields.returns_at'))->required()->seconds(false),
                            ]),
                        ]),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $actor) => $dispatch()->createTrip([
                        'vehicle_id' => (int) $data['vehicle_id'],
                        'driver_id' => (int) $data['driver_id'],
                        'departs_at' => (string) $data['departs_at'],
                        'returns_at' => (string) $data['returns_at'],
                        'activity_transport_request_id' => blank($data['activity_transport_request_id'] ?? null) ? null : (int) $data['activity_transport_request_id'],
                    ], $actor), 'transport.notifications.saved')),
            ])
            ->recordActions([
                Action::make('sheet')->label(__('transport.actions.sheet'))->icon('heroicon-o-printer')
                    ->url(fn (TransportTrip $record): string => PickupSheetPage::getUrl(['record' => $record])),
                Action::make('start')->label(__('transport.actions.start'))->requiresConfirmation()
                    ->visible(fn (TransportTrip $record): bool => $record->status === TripStatus::Planned && $can())
                    ->action(self::domainAction(fn (TransportTrip $record, array $data, User $actor) => $dispatch()->start($record, $actor), 'transport.notifications.saved')),
                Action::make('complete')->label(__('transport.actions.complete'))->color('success')->requiresConfirmation()
                    ->visible(fn (TransportTrip $record): bool => $record->status === TripStatus::InProgress && $can())
                    ->action(self::domainAction(fn (TransportTrip $record, array $data, User $actor) => $dispatch()->complete($record, $actor), 'transport.notifications.saved')),
                Action::make('cancel')->label(__('transport.actions.cancel'))->color('danger')
                    ->visible(fn (TransportTrip $record): bool => $record->status === TripStatus::Planned && $can())
                    ->schema([Textarea::make('reason')->label(__('transport.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (TransportTrip $record, array $data, User $actor) => $dispatch()->cancel($record, $actor, $data['reason'] ?? null), 'transport.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrips::route('/'),
            'sheet' => PickupSheetPage::route('/{record}/sheet'),
        ];
    }
}
