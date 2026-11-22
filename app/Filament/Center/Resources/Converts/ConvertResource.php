<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Filament\Center\Resources\Converts\Pages\EditConvert;
use App\Filament\Center\Resources\Converts\Pages\ListConverts;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Filament\Center\Resources\Converts\Schemas\ConvertForm;
use App\Filament\Center\Resources\Converts\Schemas\ConvertInfolist;
use App\Filament\Center\Resources\Converts\Tables\ConvertsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Converts are registered by the shahadah workflow (step 11); there is no create page.
 */
final class ConvertResource extends Resource
{
    protected static ?string $model = Convert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /** Names depend on disclosure and the viewer: never search or title by name globally. */
    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 2;

    /**
     * ConvertScope already limits converts to the current office. Filament's own
     * tenancy scope would be added to the Convert model for the whole request
     * and could not be lifted by TenantContext::bypass(), breaking the national
     * search and duplicate screening inside the panel.
     */
    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('converts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('converts.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user instanceof User ? app(ConvertAccess::class)->visible($query, $user) : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return ConvertForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConvertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConvertsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmploymentsRelationManager::class,
            RelationManagers\ResidencesRelationManager::class,
            RelationManagers\ContactPersonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConverts::route('/'),
            'view' => ViewConvert::route('/{record}'),
            'edit' => EditConvert::route('/{record}/edit'),
        ];
    }
}
