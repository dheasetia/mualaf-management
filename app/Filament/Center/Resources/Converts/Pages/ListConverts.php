<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\Pages;

use App\Filament\Center\Resources\Converts\ConvertResource;
use Filament\Resources\Pages\ListRecords;

final class ListConverts extends ListRecords
{
    protected static string $resource = ConvertResource::class;
}
