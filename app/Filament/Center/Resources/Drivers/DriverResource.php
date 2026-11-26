<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Drivers;

use App\Domain\Transport\Actions\ManageFleet;
use App\Domain\Transport\Enums\DriverStatus;
use App\Domain\Transport\Models\Driver;
use App\Filament\Center\Resources\Drivers\Pages\ManageDrivers;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

final class DriverResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = Driver::class;

    protected static ?string $slug = 'drivers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('transport.drivers.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport.drivers.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('transport.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('transport.drivers.model'))->columnSpanFull()->schema([
                Fieldset::make(__('forms.basic'))->columns(2)->schema([
                    TextInput::make('name')->label(__('transport.fields.name'))->required()->maxLength(255),
                    TextInput::make('phone')->label(__('transport.fields.phone'))->tel()->required()->maxLength(30),
                ]),
                Fieldset::make(__('forms.details'))->columns(2)->schema([
                    TextInput::make('license_number')->label(__('transport.fields.license_number'))->maxLength(40)->password()->revealable(),
                    DatePicker::make('license_expires_on')->label(__('transport.fields.license_expires_on')),
                ]),
                Fieldset::make(__('forms.status'))->columns(2)->schema([
                    Select::make('status')->label(__('transport.fields.status'))->required()->default(DriverStatus::Active->value)->options(collect(DriverStatus::cases())->mapWithKeys(fn (DriverStatus $case): array => [$case->value => $case->label()])->all()),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('transport.fields.name'))->searchable(),
                TextColumn::make('license_expires_on')->label(__('transport.fields.license_expires_on'))->date('Y-m-d')->placeholder('—')
                    ->color(fn (Driver $record): string => $record->license_expires_on !== null && $record->license_expires_on->isPast() ? 'danger' : 'gray'),
                TextColumn::make('status')->label(__('transport.fields.status'))->badge()->formatStateUsing(fn (DriverStatus $state): string => $state->label()),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (Driver $record): array => [
                        'name' => $record->name, 'phone' => $record->phone_encrypted,
                        'license_expires_on' => $record->license_expires_on?->toDateString(), 'status' => $record->status->value,
                    ])
                    ->using(fn (Model $record, array $data): Model => $record instanceof Driver ? self::save($record, $data) : $record),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?Driver $record, array $data): Driver
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            return app(ManageFleet::class)->saveDriver($record, $data, $user);
        } catch (RuleViolation $violation) {
            throw ValidationException::withMessages(['mountedActions.0.data.'.$violation->field => $violation->getMessage()]);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageDrivers::route('/')];
    }
}
