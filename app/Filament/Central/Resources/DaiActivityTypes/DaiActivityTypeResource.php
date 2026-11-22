<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\DaiActivityTypes;

use App\Domain\Platform\Enums\DaiActivityCategory;
use App\Domain\Platform\Models\DaiActivityType;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\DaiActivityTypes\Pages\ManageDaiActivityTypes;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

final class DaiActivityTypeResource extends MasterDataResource
{
    protected static ?string $model = DaiActivityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 70;

    protected static function translationKey(): string
    {
        return 'dai_activity_types';
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

    protected static function extraFormComponents(): array
    {
        return [
            Select::make('category')->label(__('platform.fields.category'))
                ->options(self::categoryOptions())->required(),
            Toggle::make('requires_verification')->label(__('platform.fields.requires_verification')),
            Toggle::make('active')->label(__('platform.fields.active'))->default(true),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
            TextColumn::make('category')->label(__('platform.fields.category'))
                ->formatStateUsing(fn (DaiActivityCategory $state): string => $state->label())->badge(),
            IconColumn::make('requires_verification')->label(__('platform.fields.requires_verification'))->boolean(),
            IconColumn::make('active')->label(__('platform.fields.active'))->boolean(),
        ];
    }

    protected static function extraFilters(): array
    {
        return [
            SelectFilter::make('category')->label(__('platform.fields.category'))->options(self::categoryOptions()),
            TernaryFilter::make('active')->label(__('platform.fields.active')),
        ];
    }

    /** @return array<string, string> */
    private static function categoryOptions(): array
    {
        return collect(DaiActivityCategory::cases())
            ->mapWithKeys(fn (DaiActivityCategory $category): array => [$category->value => $category->label()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDaiActivityTypes::route('/'),
        ];
    }
}
