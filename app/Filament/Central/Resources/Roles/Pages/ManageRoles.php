<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Roles\Pages;

use App\Domain\Identity\Actions\SaveCustomRole;
use App\Domain\Identity\Models\Role;
use App\Filament\Central\Resources\Roles\RoleResource;
use App\Filament\Support\UserManagement;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): Role => UserManagement::run(fn (): Role => app(SaveCustomRole::class)->handle(null, RoleResource::roleData($data), UserManagement::actor()))),
        ];
    }
}
