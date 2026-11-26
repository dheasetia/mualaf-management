<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Users;

use App\Domain\Identity\Actions\ActivateUser;
use App\Domain\Identity\Actions\AddUserToOffice;
use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Actions\SyncUserRoles;
use App\Domain\Identity\Actions\UpdateUser;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\UserManagement;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every account on the platform: platform roles, center roles and account status.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('users.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('audit.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'roleAssignments' => fn ($query) => $query->whereNull('revoked_at')->with('role'),
            'tenantMemberships' => fn ($query) => $query->where('status', MembershipStatus::Active)->with('tenant'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('users.fields.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('users.fields.email'))->searchable(),
                TextColumn::make('platform_roles')
                    ->label(__('users.fields.platform_roles'))
                    ->badge()
                    ->state(fn (User $record): array => UserManagement::roleNames($record, null)),
                TextColumn::make('offices')
                    ->label(__('users.fields.offices'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (User $record): array => $record->tenantMemberships->map(fn ($membership): string => (string) $membership->tenant?->code)->all()),
                TextColumn::make('is_active')
                    ->label(__('users.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => __($state ? 'users.status.active' : 'users.status.inactive'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label(__('users.fields.last_login'))->since()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('users.fields.status')),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (User $record): bool => UserManagement::actor()->can('update', $record))
                    ->schema([
                        TextInput::make('name')->label(__('users.fields.name'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('users.fields.email'))->email()->required()->maxLength(255),
                        ...UserManagement::passwordFields(required: false),
                    ])
                    ->fillForm(fn (User $record): array => ['name' => $record->name, 'email' => $record->email])
                    ->using(fn (User $record, array $data): User => UserManagement::run(fn (): User => app(UpdateUser::class)->handle($record, $data, UserManagement::actor()))),
                ActionGroup::make([
                    self::platformRolesAction(),
                    self::officeRolesAction(),
                    Action::make('deactivate')
                        ->label(__('users.actions.deactivate'))
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('users.actions.deactivate_confirm'))
                        ->visible(fn (User $record): bool => $record->is_active)
                        ->authorize(fn (User $record): bool => UserManagement::actor()->can('deactivate', $record))
                        ->action(function (User $record): void {
                            UserManagement::run(fn (): User => app(DeactivateUser::class)->handle($record, UserManagement::actor()));
                            Notification::make()->success()->title(__('users.notifications.deactivated'))->send();
                        }),
                    Action::make('activate')
                        ->label(__('users.actions.activate'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => ! $record->is_active)
                        ->authorize(fn (User $record): bool => UserManagement::actor()->can('deactivate', $record))
                        ->action(function (User $record): void {
                            UserManagement::run(fn (): User => app(ActivateUser::class)->handle($record, UserManagement::actor()));
                            Notification::make()->success()->title(__('users.notifications.activated'))->send();
                        }),
                ]),
            ]);
    }

    private static function platformRolesAction(): Action
    {
        return Action::make('platformRoles')
            ->label(__('users.fields.platform_roles'))
            ->icon(Heroicon::OutlinedKey)
            ->authorize(fn (User $record): bool => UserManagement::actor()->can('assignRoles', $record))
            ->fillForm(fn (User $record): array => ['roles' => UserManagement::currentRoleIds($record, null)])
            ->schema([
                CheckboxList::make('roles')->label(__('users.fields.platform_roles'))->options(UserManagement::roleOptions(RoleScope::Platform))->columns(2),
                Textarea::make('reason')->label(__('users.fields.reason'))->helperText(__('users.fields.reason_help'))->maxLength(500),
            ])
            ->action(function (User $record, array $data): void {
                UserManagement::run(fn () => app(SyncUserRoles::class)->handle($record, null, array_map(intval(...), $data['roles'] ?? []), $data['reason'] ?? null, UserManagement::actor()));
                Notification::make()->success()->title(__('users.notifications.roles_saved'))->send();
            });
    }

    /**
     * Center roles from the platform: also how the first staff of a new center
     * get in. Not yet staff there: the user is added to the center first.
     */
    private static function officeRolesAction(): Action
    {
        return Action::make('officeRoles')
            ->label(__('users.actions.office_roles'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->authorize(fn (User $record): bool => UserManagement::actor()->can('assignRoles', $record))
            ->schema([
                Select::make('tenant_id')
                    ->label(__('users.fields.office'))
                    ->required()
                    ->live()
                    ->options(fn (): array => LocalizedName::options(Tenant::query()->where('status', TenantStatus::Active)))
                    ->afterStateUpdated(function (Set $set, ?string $state, User $record): void {
                        $tenant = $state === null ? null : Tenant::query()->find($state);
                        $set('roles', $tenant === null ? [] : UserManagement::currentRoleIds($record, $tenant));
                    }),
                CheckboxList::make('roles')
                    ->label(__('users.fields.office_roles'))
                    ->options(UserManagement::roleOptions(RoleScope::Tenant))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('tenant_id'))),
                Textarea::make('reason')->label(__('users.fields.reason'))->helperText(__('users.fields.reason_help'))->maxLength(500),
            ])
            ->action(function (User $record, array $data): void {
                UserManagement::run(function () use ($record, $data): void {
                    $tenant = Tenant::query()->findOrFail($data['tenant_id']);
                    $roleIds = array_map(intval(...), $data['roles'] ?? []);
                    $actor = UserManagement::actor();

                    if (! $record->tenantMemberships()->where('tenant_id', $tenant->id)->where('status', MembershipStatus::Active)->exists()) {
                        app(AddUserToOffice::class)->handle($record, $tenant, Role::query()->whereKey($roleIds)->where('scope_type', RoleScope::Tenant)->get(), $actor);

                        return;
                    }

                    app(SyncUserRoles::class)->handle($record, $tenant, $roleIds, $data['reason'] ?? null, $actor);
                });
                Notification::make()->success()->title(__('users.notifications.roles_saved'))->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
