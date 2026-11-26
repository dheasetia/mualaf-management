<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Roles;

use App\Domain\Identity\Actions\DeleteCustomRole;
use App\Domain\Identity\Actions\SaveCustomRole;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Filament\Central\Resources\Roles\Pages\ManageRoles;
use App\Filament\Support\RoleTable;
use App\Filament\Support\UserManagement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * All roles. System roles (RoleCatalog) are read-only; custom roles are made,
 * changed and, while never assigned, deleted here.
 */
final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

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

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label(__('roles.fields.name_ar'))->required()->maxLength(255),
            TextInput::make('name_en')->label(__('roles.fields.name_en'))->required()->maxLength(255),
            Select::make('scope_type')
                ->label(__('roles.fields.scope'))
                ->helperText(__('roles.fields.scope_help'))
                ->required()
                ->live()
                ->options(collect(RoleScope::cases())->mapWithKeys(fn (RoleScope $scope): array => [$scope->value => __('roles.scopes.'.$scope->value)])->all())
                ->disabledOn('edit'),
            Textarea::make('description')->label(__('roles.fields.description'))->maxLength(500),
            CheckboxList::make('permissions')
                ->label(__('roles.fields.permissions'))
                ->helperText(__('roles.fields.permissions_help'))
                ->required()
                ->options(fn (Get $get): array => RoleTable::permissionOptions(self::scopeOf($get('scope_type'))))
                ->searchable()
                ->bulkToggleable()
                ->columns(3)
                ->columnSpanFull(),
        ]);
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
            ->columns(RoleTable::columns(withScope: true))
            ->filters([
                SelectFilter::make('scope_type')
                    ->label(__('roles.fields.scope'))
                    ->options(collect(RoleScope::cases())->mapWithKeys(fn (RoleScope $scope): array => [$scope->value => __('roles.scopes.'.$scope->value)])->all()),
                TernaryFilter::make('is_system')->label(__('roles.types.system')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Role $record): array => [
                        ...$data,
                        'scope_type' => $record->scope_type->value,
                        'permissions' => $record->permissions()->orderBy('code')->pluck('code')->all(),
                    ])
                    ->using(fn (Role $record, array $data): Role => UserManagement::run(fn (): Role => app(SaveCustomRole::class)->handle($record, self::roleData($data), UserManagement::actor()))),
                DeleteAction::make()
                    ->using(function (Role $record): bool {
                        UserManagement::run(fn () => app(DeleteCustomRole::class)->handle($record, UserManagement::actor()));

                        return true;
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name_ar: string, name_en: string, scope_type: string, description: string|null, permissions: list<string>}
     */
    public static function roleData(array $data): array
    {
        return [
            'name_ar' => (string) $data['name_ar'],
            'name_en' => (string) $data['name_en'],
            'scope_type' => (string) ($data['scope_type'] ?? ''),
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'permissions' => array_values(array_map(strval(...), (array) ($data['permissions'] ?? []))),
        ];
    }

    private static function scopeOf(mixed $value): ?RoleScope
    {
        return match (true) {
            $value instanceof RoleScope => $value,
            is_string($value) => RoleScope::tryFrom($value),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
