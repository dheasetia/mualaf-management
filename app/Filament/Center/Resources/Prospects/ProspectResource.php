<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Prospects;

use App\Domain\Prospects\Models\Prospect;
use App\Domain\Prospects\Services\ProspectVisibility;
use App\Filament\Center\Resources\Prospects\Pages\CreateProspect;
use App\Filament\Center\Resources\Prospects\Pages\EditProspect;
use App\Filament\Center\Resources\Prospects\Pages\ListProspects;
use App\Filament\Center\Resources\Prospects\Pages\ViewProspect;
use App\Filament\Center\Resources\Prospects\Schemas\ProspectForm;
use App\Filament\Center\Resources\Prospects\Schemas\ProspectInfolist;
use App\Filament\Center\Resources\Prospects\Tables\ProspectsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('prospects.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prospects.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    /**
     * Da'i with prospects.view_own see only prospects they are assigned to;
     * records outside this query are not found on view or edit pages either.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user instanceof User ? app(ProspectVisibility::class)->apply($query, $user) : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return ProspectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProspectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProspectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProspects::route('/'),
            'create' => CreateProspect::route('/create'),
            'view' => ViewProspect::route('/{record}'),
            'edit' => EditProspect::route('/{record}/edit'),
        ];
    }
}
