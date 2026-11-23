<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Pickups;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Converts\Services\PickupDirectory;
use App\Domain\Transport\Actions\Dispatch;
use App\Domain\Transport\Actions\PickupRequests;
use App\Domain\Transport\Enums\PickupLocationType;
use App\Domain\Transport\Enums\PickupStatus;
use App\Domain\Transport\Enums\TripStatus;
use App\Domain\Transport\Models\TransportRequest;
use App\Domain\Transport\Models\TransportTrip;
use App\Filament\Center\Resources\Pickups\Pages\ManagePickups;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PickupResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = TransportRequest::class;

    protected static ?string $slug = 'pickups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 13;

    public static function getModelLabel(): string
    {
        return __('transport.pickups.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport.pickups.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('transport.navigation_group');
    }

    public static function table(Table $table): Table
    {
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;
        $can = fn (string $permission): bool => $user()?->hasPermission($permission) ?? false;
        $dispatch = fn (): Dispatch => app(Dispatch::class);
        $pickups = fn (): PickupRequests => app(PickupRequests::class);
        $timezone = (string) config('app.display_timezone');

        return $table
            ->defaultSort('pickup_at')
            ->columns([
                TextColumn::make('pickup_at')->label(__('transport.fields.pickup_at'))->dateTime('Y-m-d H:i', timezone: $timezone)->sortable(),
                TextColumn::make('passenger')->label(__('transport.fields.convert'))
                    ->state(fn (TransportRequest $record): string => ($u = $user()) !== null ? app(PickupDirectory::class)->label($u, $record->convert_id) : '—'),
                TextColumn::make('pickup_location_type')->label(__('transport.fields.pickup_location_type'))->formatStateUsing(fn (PickupLocationType $state): string => $state->label()),
                TextColumn::make('pickup_address')->label(__('transport.fields.pickup_address'))->limit(40)->placeholder('—'),
                TextColumn::make('trip_id')->label(__('transport.fields.trip'))->placeholder('—'),
                IconColumn::make('printable')->label(__('transport.fields.disclosure'))->boolean()
                    ->state(fn (TransportRequest $record): bool => $record->disclosure_approved_by !== null || ! app(PickupDirectory::class)->isHighConfidentiality($record->convert_id)),
                TextColumn::make('status')->label(__('transport.fields.status'))->badge()->formatStateUsing(fn (PickupStatus $state): string => $state->label()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('transport.fields.status'))->options(collect(PickupStatus::cases())->mapWithKeys(fn (PickupStatus $case): array => [$case->value => $case->label()])->all()),
            ])
            ->headerActions([
                Action::make('requestPickup')->label(__('transport.actions.request_pickup'))->visible(fn (): bool => $can('transport.request'))
                    ->schema([
                        Select::make('convert_id')->label(__('transport.fields.convert'))->required()->searchable()->live()
                            ->options(fn (): array => ($u = $user()) === null ? [] : app(ConvertAccess::class)->visible(Convert::query(), $u)->orderBy('full_name')->limit(500)->get()
                                ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($u, $convert)])->all()),
                        Select::make('pickup_location_type')->label(__('transport.fields.pickup_location_type'))->required()->live()->options(collect(PickupLocationType::cases())->mapWithKeys(fn (PickupLocationType $case): array => [$case->value => $case->label()])->all()),
                        Select::make('residence_id')->label(__('transport.fields.residence'))->required()
                            ->visible(fn (Get $get): bool => $get('pickup_location_type') === PickupLocationType::Residence->value)
                            ->options(fn (Get $get): array => blank($get('convert_id')) ? [] : app(PickupDirectory::class)->residenceOptions((int) $get('convert_id'))),
                        TextInput::make('pickup_address')->label(__('transport.fields.pickup_address'))->maxLength(500)
                            ->visible(fn (Get $get): bool => $get('pickup_location_type') === PickupLocationType::Custom->value),
                        TextInput::make('latitude')->label(__('transport.fields.latitude'))->numeric()->minValue(-90)->maxValue(90)
                            ->visible(fn (Get $get): bool => $get('pickup_location_type') === PickupLocationType::Custom->value),
                        TextInput::make('longitude')->label(__('transport.fields.longitude'))->numeric()->minValue(-180)->maxValue(180)
                            ->visible(fn (Get $get): bool => $get('pickup_location_type') === PickupLocationType::Custom->value),
                        Select::make('contact_person_id')->label(__('transport.fields.contact_person'))
                            ->options(fn (Get $get): array => blank($get('convert_id')) ? [] : app(PickupDirectory::class)->contactOptions((int) $get('convert_id'))),
                        DateTimePicker::make('pickup_at')->label(__('transport.fields.pickup_at'))->required()->seconds(false),
                        TextInput::make('notes')->label(__('transport.fields.notes'))->maxLength(1000),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $actor) => $pickups()->request(Convert::query()->findOrFail((int) $data['convert_id']), $data, $actor), 'transport.notifications.saved')),
            ])
            ->recordActions([
                Action::make('approveDisclosure')->label(__('transport.actions.approve_disclosure'))->requiresConfirmation()
                    ->visible(fn (TransportRequest $record): bool => $record->disclosure_approved_by === null && $can('converts.view_high_confidentiality')
                        && app(PickupDirectory::class)->isHighConfidentiality($record->convert_id))
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $pickups()->approveDisclosure($record, $actor), 'transport.notifications.saved')),
                Action::make('assign')->label(__('transport.actions.assign'))
                    ->visible(fn (TransportRequest $record): bool => $record->status === PickupStatus::Requested && $can('transport.dispatch'))
                    ->schema([
                        Select::make('trip_id')->label(__('transport.fields.trip'))->required()
                            ->options(fn (): array => TransportTrip::query()->with('vehicle')->where('status', TripStatus::Planned)->orderBy('departs_at')->get()
                                ->mapWithKeys(fn (TransportTrip $trip): array => [$trip->id => $trip->departs_at->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i').' · '.$trip->vehicle->plate_number])->all()),
                    ])
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $dispatch()->assign($record, TransportTrip::query()->findOrFail((int) $data['trip_id']), $actor), 'transport.notifications.saved')),
                Action::make('unassign')->label(__('transport.actions.unassign'))->color('gray')
                    ->visible(fn (TransportRequest $record): bool => $record->status === PickupStatus::Assigned && $can('transport.dispatch'))
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $dispatch()->unassign($record, $actor), 'transport.notifications.saved')),
                Action::make('pickedUp')->label(__('transport.actions.picked_up'))->color('success')
                    ->visible(fn (TransportRequest $record): bool => $record->status === PickupStatus::Assigned && $can('transport.dispatch'))
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $dispatch()->mark($record, PickupStatus::PickedUp, $actor), 'transport.notifications.saved')),
                Action::make('noShow')->label(__('transport.actions.no_show'))->color('warning')
                    ->visible(fn (TransportRequest $record): bool => $record->status === PickupStatus::Assigned && $can('transport.dispatch'))
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $dispatch()->mark($record, PickupStatus::NoShow, $actor), 'transport.notifications.saved')),
                Action::make('cancel')->label(__('transport.actions.cancel'))->color('danger')->requiresConfirmation()
                    ->visible(fn (TransportRequest $record): bool => $record->status->canTransitionTo(PickupStatus::Cancelled) && ($record->requested_by === auth()->id() || $can('transport.dispatch')))
                    ->action(self::domainAction(fn (TransportRequest $record, array $data, User $actor) => $pickups()->cancel($record, $actor), 'transport.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePickups::route('/')];
    }
}
