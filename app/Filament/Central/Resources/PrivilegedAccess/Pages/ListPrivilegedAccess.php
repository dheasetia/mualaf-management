<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\PrivilegedAccess\Pages;

use App\Filament\Central\Resources\PrivilegedAccess\PrivilegedAccessResource;
use Filament\Resources\Pages\ListRecords;

final class ListPrivilegedAccess extends ListRecords
{
    protected static string $resource = PrivilegedAccessResource::class;
}
