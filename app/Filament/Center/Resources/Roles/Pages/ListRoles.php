<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Roles\Pages;

use App\Filament\Center\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ManageRecords;

final class ListRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
