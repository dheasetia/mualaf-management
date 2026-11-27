<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Users\Pages;

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Enums\RoleScope;
use App\Domain\Identity\Models\Role;
use App\Filament\Central\Resources\Users\UserResource;
use App\Filament\Support\UserManagement;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('users.actions.create'))
                ->schema([
                    Section::make()->columnSpanFull()->schema([
                        Fieldset::make(__('forms.account'))->columns(2)->schema([
                            TextInput::make('name')->label(__('users.fields.name'))->required()->maxLength(255),
                            TextInput::make('email')->label(__('users.fields.email'))->email()->required()->maxLength(255),
                        ]),
                        Fieldset::make(__('forms.security'))->columns(2)->schema([
                            ...UserManagement::passwordFields(required: true),
                        ]),
                        Fieldset::make(__('users.fields.platform_roles'))->columns(1)->schema([
                            CheckboxList::make('roles')
                                ->label(__('users.fields.platform_roles'))
                                ->options(UserManagement::roleOptions(RoleScope::Platform))
                                ->columns(2)
                                ->visible(fn (): bool => UserManagement::actor()->hasPermission('users.assign_roles')),
                        ]),
                    ]),
                ])
                ->using(fn (array $data): User => UserManagement::run(fn (): User => DB::transaction(function () use ($data): User {
                    $actor = UserManagement::actor();
                    $user = app(CreateUser::class)->handle($data, $actor);
                    $roleIds = array_map(intval(...), (array) ($data['roles'] ?? []));

                    foreach (Role::query()->whereKey($roleIds)->where('scope_type', RoleScope::Platform)->get() as $role) {
                        app(AssignRole::class)->handle($user, $role, null, $actor);
                    }

                    return $user;
                }))),
        ];
    }
}
