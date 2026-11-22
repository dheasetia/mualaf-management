<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\BudgetCategories;

use App\Domain\Platform\Models\BudgetCategory;
use App\Filament\Central\MasterData\MasterDataResource;
use App\Filament\Central\Resources\BudgetCategories\Pages\ManageBudgetCategories;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;

final class BudgetCategoryResource extends MasterDataResource
{
    protected static ?string $model = BudgetCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 60;

    protected static function translationKey(): string
    {
        return 'budget_categories';
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
            Toggle::make('active')->label(__('platform.fields.active'))->default(true),
        ];
    }

    protected static function extraColumns(): array
    {
        return [
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
            'index' => ManageBudgetCategories::route('/'),
        ];
    }
}
