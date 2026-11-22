<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais;

use App\Domain\Dai\Models\Dai;
use App\Filament\Center\Resources\Dais\Pages\CreateDai;
use App\Filament\Center\Resources\Dais\Pages\EditDai;
use App\Filament\Center\Resources\Dais\Pages\ListDais;
use App\Filament\Center\Resources\Dais\Schemas\DaiForm;
use App\Filament\Center\Resources\Dais\Tables\DaisTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class DaiResource extends Resource
{
    protected static ?string $model = Dai::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('dai.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dai.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return DaiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DaisTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDais::route('/'),
            'create' => CreateDai::route('/create'),
            'edit' => EditDai::route('/{record}/edit'),
        ];
    }
}
