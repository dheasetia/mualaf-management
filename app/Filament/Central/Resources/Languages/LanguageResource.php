<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Languages;

use App\Domain\Platform\Models\Language;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\Languages\Pages\ManageLanguages;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;

final class LanguageResource extends MasterDataResource
{
    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 40;

    protected static function translationKey(): string
    {
        return 'languages';
    }

    protected static function codePattern(): string
    {
        return '/^[a-z]{2,3}$/i';
    }

    protected static function codeIsUppercase(): bool
    {
        return false;
    }

    protected static function extraFormComponents(): array
    {
        return [
            Toggle::make('is_rtl')->label(__('platform.fields.is_rtl')),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            IconColumn::make('is_rtl')->label(__('platform.fields.is_rtl'))->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLanguages::route('/'),
        ];
    }
}
