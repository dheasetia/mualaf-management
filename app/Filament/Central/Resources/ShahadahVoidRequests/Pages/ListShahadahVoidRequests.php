<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ShahadahVoidRequests\Pages;

use App\Filament\Central\Resources\ShahadahVoidRequests\ShahadahVoidRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListShahadahVoidRequests extends ListRecords
{
    protected static string $resource = ShahadahVoidRequestResource::class;
}
