<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Religions;

use App\Domain\Platform\Models\Religion;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\Religions\Pages\ManageReligions;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;

final class ReligionResource extends MasterDataResource
{
    protected static ?string $model = Religion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 50;

    protected static function translationKey(): string
    {
        return 'religions';
    }

    protected static function codePattern(): string
    {
        return '/^[a-z][a-z0-9_]*$/i';
    }

    protected static function codeIsUppercase(): bool
    {
        return false;
    }

    protected static function codeMaxLength(): int
    {
        return 40;
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
            Toggle::make('active')->label(__('platform.fields.active'))->default(true),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('sort_order')->label(__('platform.fields.sort_order'))->sortable(),
            IconColumn::make('active')->label(__('platform.fields.active'))->boolean(),
        ];
    }

    protected static function extraFilters(): array
    {
        return [
            TernaryFilter::make('active')->label(__('platform.fields.active')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReligions::route('/'),
        ];
    }
}
