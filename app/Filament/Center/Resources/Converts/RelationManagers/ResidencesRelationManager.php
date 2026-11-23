<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Enums\ResidenceType;
use App\Domain\Converts\Models\ConvertResidence;
use App\Domain\Platform\Models\City;
use App\Support\Geo\GoogleMapsLink;
use App\Support\Localization\LocalizedName;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ResidencesRelationManager extends LocationRelationManager
{
    protected static string $relationship = 'residences';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('converts.locations.residences');
    }

    protected static function recordClass(): string
    {
        return ConvertResidence::class;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('residence_type')->label(__('converts.locations.residence_type'))->required()
                ->options(collect(ResidenceType::cases())->mapWithKeys(fn (ResidenceType $case): array => [$case->value => $case->label()])->all()),
            TextInput::make('name')->label(__('converts.locations.name'))->maxLength(255),
            Select::make('city_id')->label(__('converts.locations.city'))->searchable()
                ->options(fn (): array => LocalizedName::options(City::query())),
            TextInput::make('district')->label(__('converts.locations.district'))->maxLength(255),
            TextInput::make('address')->label(__('converts.locations.address'))->maxLength(255),
            TextInput::make('building_no')->label(__('converts.locations.building_no'))->maxLength(20),
            TextInput::make('room_no')->label(__('converts.locations.room_no'))->maxLength(20),
            TextInput::make('google_maps_url')->label(__('converts.locations.google_maps_url'))->url()->maxLength(500)
                ->helperText(__('companies.help.google_maps_url'))->columnSpanFull(),
            TextInput::make('latitude')->label(__('converts.locations.latitude'))->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label(__('converts.locations.longitude'))->numeric()->minValue(-180)->maxValue(180),
            Toggle::make('is_pickup_point')->label(__('converts.locations.is_pickup_point')),
            TextInput::make('pickup_instructions')->label(__('converts.locations.pickup_instructions'))->maxLength(500),
            Toggle::make('is_current')->label(__('converts.locations.is_current'))->default(true),
            DatePicker::make('started_at')->label(__('converts.locations.started_at')),
            DatePicker::make('ended_at')->label(__('converts.locations.ended_at'))->afterOrEqual('started_at'),
            Textarea::make('notes')->label(__('converts.locations.notes'))->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('city'))
            ->defaultSort('is_current', 'desc')
            ->columns([
                TextColumn::make('residence_type')->label(__('converts.locations.residence_type'))
                    ->formatStateUsing(fn (ResidenceType $state): string => $state->label()),
                TextColumn::make('name')->label(__('converts.locations.name'))->placeholder('—'),
                TextColumn::make('city.'.LocalizedName::column())->label(__('converts.locations.city'))->placeholder('—'),
                IconColumn::make('is_pickup_point')->label(__('converts.locations.is_pickup_point'))->boolean(),
                IconColumn::make('is_current')->label(__('converts.locations.is_current'))->boolean(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                Action::make('map')->label(__('companies.actions.open_map'))->icon('heroicon-o-map-pin')
                    ->url(fn (ConvertResidence $record): ?string => GoogleMapsLink::forCoordinates($record->latitude, $record->longitude) ?? $record->google_maps_url, shouldOpenInNewTab: true)
                    ->visible(fn (ConvertResidence $record): bool => $record->latitude !== null || $record->google_maps_url !== null),
                $this->editAction(),
            ]);
    }
}
