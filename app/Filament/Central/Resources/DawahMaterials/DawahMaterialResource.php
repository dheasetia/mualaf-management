<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\DawahMaterials;

use App\Domain\Dawah\Models\DawahMaterial;
use App\Filament\Central\Resources\DawahMaterials\Pages\ManageDawahMaterials;
use App\Filament\Schemas\DawahMaterialSchema;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * National dawah materials (08 §8.4), drafted, reviewed and approved in
 * Central and read by every office.
 */
final class DawahMaterialResource extends Resource
{
    protected static ?string $model = DawahMaterial::class;

    protected static ?string $slug = 'dawah-materials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('dawah.materials.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dawah.materials.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(DawahMaterialSchema::form());
    }

    public static function table(Table $table): Table
    {
        return DawahMaterialSchema::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDawahMaterials::route('/'),
        ];
    }
}
