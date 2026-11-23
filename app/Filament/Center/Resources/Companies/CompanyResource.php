<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Companies;

use App\Domain\Companies\Models\Company;
use App\Filament\Center\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Schemas\CompanySchema;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Companies an office may use: the national directory and its own (CompanyScope).
 */
final class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name_ar';

    protected static ?int $navigationSort = 30;

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('companies.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('companies.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['city', 'tenant']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(CompanySchema::form());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name_ar')
            ->columns(CompanySchema::columns())
            ->recordActions([
                CompanySchema::mapAction(),
                EditAction::make()->using(fn (Model $record, array $data): Model => $record instanceof Company ? CompanySchema::save($record, $data) : $record),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }
}
