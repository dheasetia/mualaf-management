<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Staff;

use App\Domain\Identity\Actions\RemoveUserFromOffice;
use App\Domain\Identity\Actions\SyncUserRoles;
use App\Domain\Identity\Actions\UpdateUser;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Filament\Center\Resources\Staff\Pages\ManageStaff;
use App\Filament\Support\UserManagement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff of the current office: accounts with an active membership here.
 */
final class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('users.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.staff_plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('audit.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = UserManagement::tenant()->id;

        return parent::getEloquentQuery()
            ->whereHas('tenantMemberships', fn (Builder $query) => $query->where('tenant_id', $tenantId)->where('status', MembershipStatus::Active))
            ->with(['roleAssignments' => fn ($query) => $query->whereNull('revoked_at')->where('tenant_id', $tenantId)->with('role')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('users.fields.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('users.fields.email'))->searchable(),
                TextColumn::make('office_roles')
                    ->label(__('users.fields.office_roles'))
                    ->badge()
                    ->state(fn (User $record): array => UserManagement::roleNames($record, UserManagement::tenant()->id)),
                TextColumn::make('is_active')
                    ->label(__('users.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => __($state ? 'users.status.active' : 'users.status.inactive'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label(__('users.fields.last_login'))->since()->placeholder('—'),
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
                Action::make('roles')
                    ->label(__('users.actions.roles'))
                    ->icon(Heroicon::OutlinedKey)
                    ->authorize(fn (User $record): bool => UserManagement::actor()->can('assignRoles', $record))
                    ->fillForm(fn (User $record): array => ['roles' => UserManagement::currentRoleIds($record, UserManagement::tenant())])
                    ->schema([
                        CheckboxList::make('roles')->label(__('users.fields.office_roles'))->options(UserManagement::roleOptions(RoleScope::Tenant))->columns(2),
                        Textarea::make('reason')->label(__('users.fields.reason'))->helperText(__('users.fields.reason_help'))->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        UserManagement::run(fn () => app(SyncUserRoles::class)->handle(
                            $record,
                            UserManagement::tenant(),
                            array_map(intval(...), $data['roles'] ?? []),
                            $data['reason'] ?? null,
                            UserManagement::actor(),
                        ));
                        Notification::make()->success()->title(__('users.notifications.roles_saved'))->send();
                    }),
                Action::make('removeFromOffice')
                    ->label(__('users.actions.remove_from_office'))
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->authorize(fn (User $record): bool => UserManagement::actor()->can('removeFromOffice', $record))
                    ->modalDescription(__('users.actions.remove_from_office_confirm'))
                    ->schema([
                        Textarea::make('reason')->label(__('users.fields.reason'))->required()->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        UserManagement::run(fn () => app(RemoveUserFromOffice::class)->handle($record, UserManagement::tenant(), (string) $data['reason'], UserManagement::actor()));
                        Notification::make()->success()->title(__('users.notifications.removed'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
