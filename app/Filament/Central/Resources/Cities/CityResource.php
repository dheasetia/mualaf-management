<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Cities;

use App\Domain\Platform\Models\City;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\Cities\Pages\ManageCities;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

final class CityResource extends MasterDataResource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 20;

    protected static function translationKey(): string
    {
        return 'cities';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('region');
    }

    protected static function codeMaxLength(): int
    {
        return 20;
    }

    protected static function extraFormComponents(): array
    {
        return [
            Select::make('region_id')->label(__('platform.fields.region'))
                ->relationship('region', 'name_ar')
                ->searchable()->preload()->required(),
            TextInput::make('latitude')->label(__('platform.fields.latitude'))
                ->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label(__('platform.fields.longitude'))
                ->numeric()->minValue(-180)->maxValue(180),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('region.name_ar')->label(__('platform.fields.region'))->sortable(),
        ];
    }

    protected static function extraFilters(): array
    {
        return [
            SelectFilter::make('region')->label(__('platform.fields.region'))
                ->relationship('region', 'name_ar')->preload(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCities::route('/'),
        ];
    }
}
