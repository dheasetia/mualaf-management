<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Vehicles;

use App\Domain\Transport\Actions\ManageFleet;
use App\Domain\Transport\Enums\VehicleStatus;
use App\Domain\Transport\Enums\VehicleType;
use App\Domain\Transport\Models\Vehicle;
use App\Filament\Center\Resources\Vehicles\Pages\ManageVehicles;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class VehicleResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = Vehicle::class;

    protected static ?string $slug = 'vehicles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('transport.vehicles.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport.vehicles.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('transport.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Fieldset::make(__('forms.vehicle'))->columns(2)->schema([
                    TextInput::make('vehicle_number')->label(__('transport.fields.vehicle_number'))->required()->maxLength(30),
                    TextInput::make('plate_number')->label(__('transport.fields.plate_number'))->required()->maxLength(20),
                    Select::make('vehicle_type')->label(__('transport.fields.vehicle_type'))->required()->options(collect(VehicleType::cases())->mapWithKeys(fn (VehicleType $case): array => [$case->value => $case->label()])->all()),
                    TextInput::make('capacity')->label(__('transport.fields.capacity'))->required()->integer()->minValue(1)->maxValue(80),
                ]),
                Fieldset::make(__('forms.status'))->columns(2)->schema([
                    Select::make('status')->label(__('transport.fields.status'))->required()->default(VehicleStatus::Available->value)->options(collect(VehicleStatus::cases())->mapWithKeys(fn (VehicleStatus $case): array => [$case->value => $case->label()])->all()),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle_number')->label(__('transport.fields.vehicle_number'))->searchable(),
                TextColumn::make('plate_number')->label(__('transport.fields.plate_number'))->searchable(),
                TextColumn::make('vehicle_type')->label(__('transport.fields.vehicle_type'))->formatStateUsing(fn (VehicleType $state): string => $state->label()),
                TextColumn::make('capacity')->label(__('transport.fields.capacity')),
                TextColumn::make('status')->label(__('transport.fields.status'))->badge()->formatStateUsing(fn (VehicleStatus $state): string => $state->label()),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof Vehicle ? self::save($record, $data) : $record),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?Vehicle $record, array $data): Vehicle
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageFleet::class)->saveVehicle($record, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageVehicles::route('/')];
    }
}
