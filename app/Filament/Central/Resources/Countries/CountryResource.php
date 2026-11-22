<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Countries;

use App\Domain\Platform\Models\Country;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\Countries\Pages\ManageCountries;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

final class CountryResource extends MasterDataResource
{
    protected static ?string $model = Country::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 30;

    protected static function translationKey(): string
    {
        return 'countries';
    }

    protected static function defaultSortColumn(): string
    {
        return 'iso2';
    }

    protected static function codeComponents(): array
    {
        return [
            self::codeInput('iso2', '/^[a-z]{2}$/i', 2, uppercase: true),
            self::codeInput('iso3', '/^[a-z]{3}$/i', 3, uppercase: true),
        ];
    }

    protected static function codeColumns(): array
    {
        return [
            TextColumn::make('iso2')->label(__('platform.fields.iso2'))->searchable()->sortable()->fontFamily('mono'),
            TextColumn::make('iso3')->label(__('platform.fields.iso3'))->searchable()->fontFamily('mono'),
        ];
    }

    protected static function extraFormComponents(): array
    {
        return [
            TextInput::make('phone_code')->label(__('platform.fields.phone_code'))
                ->regex('/^\\+\\d{1,4}$/')->maxLength(8)->placeholder('+966')
                ->extraInputAttributes(['dir' => 'ltr']),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('phone_code')->label(__('platform.fields.phone_code')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCountries::route('/'),
        ];
    }
}
