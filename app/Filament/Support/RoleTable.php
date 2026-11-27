<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Rbac\RoleCatalog;
use App\Support\Localization\LocalizedName;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

/**
 * Columns and the permission view shared by the role screens of both panels.
 */
final class RoleTable
{
    /** @return list<TextColumn> */
    public static function columns(bool $withScope): array
    {
        return array_values(array_filter([
            TextColumn::make(LocalizedName::column())->label(__('roles.fields.name'))->searchable(['name_ar', 'name_en'])->sortable(),
            TextColumn::make('code')->label(__('roles.fields.code'))->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
            $withScope ? TextColumn::make('scope_type')->label(__('roles.fields.scope'))->badge()->formatStateUsing(fn (RoleScope $state): string => __('roles.scopes.'.$state->value)) : null,
            TextColumn::make('is_system')
                ->label(__('roles.fields.type'))
                ->badge()
                ->formatStateUsing(fn (bool $state): string => __($state ? 'roles.types.system' : 'roles.types.custom'))
                ->color(fn (bool $state): string => $state ? 'gray' : 'primary'),
            TextColumn::make('permissions_count')->label(__('roles.fields.permissions_count'))->counts('permissions'),
        ]));
    }

    /** @return list<TextEntry> */
    public static function details(): array
    {
        return [
            TextEntry::make('description')->label(__('roles.fields.description'))->placeholder('—'),
            TextEntry::make('type_note')
                ->hiddenLabel()
                ->state(__('roles.system_note'))
                ->visible(fn (Role $record): bool => $record->is_system)
                ->color('gray'),
            TextEntry::make('permission_codes')
                ->label(__('roles.fields.permissions'))
                ->badge()
                ->state(fn (Role $record): array => $record->permissions()->orderBy('code')->pluck('code')->all()),
        ];
    }

    /**
     * Permission checkbox options for a scope, labelled "module · action".
     *
     * @return array<string, string>
     */
    public static function permissionOptions(?RoleScope $scope): array
    {
        if ($scope === null) {
            return [];
        }

        $allowed = RoleCatalog::assignablePermissions($scope);

        return Permission::query()
            ->whereIn('code', $allowed)
            ->orderBy('code')
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [$code => str_replace('.', ' · ', $code)])
            ->all();
    }
}
