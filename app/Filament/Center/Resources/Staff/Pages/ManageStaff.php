<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Staff\Pages;

use App\Domain\Identity\Actions\AddUserToOffice;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Exceptions\IdentityRuleViolation;
use App\Domain\Identity\Models\Role;
use App\Filament\Center\Resources\Staff\StaffResource;
use App\Filament\Support\UserManagement;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('users.actions.create'))
                ->schema([
                    TextInput::make('name')->label(__('users.fields.name'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('users.fields.email'))->email()->required()->maxLength(255),
                    ...UserManagement::passwordFields(required: true),
                    $this->rolesField(),
                ])
                ->using(fn (array $data): User => UserManagement::run(fn (): User => DB::transaction(function () use ($data): User {
                    $actor = UserManagement::actor();
                    $user = app(CreateUser::class)->handle($data, $actor);

                    return app(AddUserToOffice::class)->handle($user, UserManagement::tenant(), $this->roles($data), $actor);
                }))),
            Action::make('addExisting')
                ->label(__('users.actions.add_existing'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->authorize(fn (): bool => UserManagement::actor()->can('create', User::class))
                ->modalDescription(__('users.actions.add_existing_help'))
                ->schema([
                    TextInput::make('email')->label(__('users.fields.email'))->email()->required(),
                    $this->rolesField(),
                ])
                ->action(function (array $data): void {
                    UserManagement::run(function () use ($data): void {
                        $user = User::query()->where('email', Str::lower(trim((string) $data['email'])))->first()
                            ?? throw IdentityRuleViolation::userNotFound();

                        app(AddUserToOffice::class)->handle($user, UserManagement::tenant(), $this->roles($data), UserManagement::actor());
                    });
                    Notification::make()->success()->title(__('users.notifications.saved'))->send();
                }),
        ];
    }

    private function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->label(__('users.fields.office_roles'))
            ->options(UserManagement::roleOptions(RoleScope::Tenant))
            ->columns(2)
            ->visible(fn (): bool => UserManagement::actor()->hasPermission('users.assign_roles'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return iterable<Role>
     */
    private function roles(array $data): iterable
    {
        $ids = array_map(intval(...), (array) ($data['roles'] ?? []));

        return Role::query()->whereKey($ids)->where('scope_type', RoleScope::Tenant)->get();
    }
}
