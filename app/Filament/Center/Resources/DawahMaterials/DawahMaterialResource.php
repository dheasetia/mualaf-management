<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahMaterials;

use App\Domain\Dawah\Models\DawahMaterial;
use App\Filament\Center\Resources\DawahMaterials\Pages\ManageDawahMaterials;
use App\Filament\Schemas\DawahMaterialSchema;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * National and office dawah materials (08 §8.4); the office manages its own.
 */
final class DawahMaterialResource extends Resource
{
    protected static ?string $model = DawahMaterial::class;

    protected static ?string $slug = 'dawah-materials';

    /** MaterialScope already limits the list to national materials and this office's own. */
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 3;

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
        return __('dawah.navigation_group');
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
