<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Regions;

use App\Domain\Platform\Models\Region;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\Regions\Pages\ManageRegions;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

final class RegionResource extends MasterDataResource
{
    protected static ?string $model = Region::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 10;

    protected static function translationKey(): string
    {
        return 'regions';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('cities');
    }

    protected static function defaultSortColumn(): string
    {
        return 'sort_order';
    }

    protected static function extraFormComponents(): array
    {
        return [
            TextInput::make('sort_order')->label(__('platform.fields.sort_order'))
                ->integer()->minValue(0)->maxValue(65535)->default(0)->required(),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('cities_count')->label(__('platform.regions.cities_count'))->numeric(),
            TextColumn::make('sort_order')->label(__('platform.fields.sort_order'))->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegions::route('/'),
        ];
    }
}
