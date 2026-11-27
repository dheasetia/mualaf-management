<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Roles;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Filament\Center\Resources\Roles\Pages\ListRoles;
use App\Filament\Support\RoleTable;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Office roles and their permissions, read-only. Custom roles are made in Central.
 */
final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('roles.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('roles.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('audit.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('scope_type', RoleScope::Tenant);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(RoleTable::details());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('is_system', 'desc')
            ->paginated(false)
            ->columns(RoleTable::columns(withScope: false))
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRoles::route('/')];
    }
}
