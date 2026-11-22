<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Regions\Pages;

use App\Filament\Central\Resources\Regions\RegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageRegions extends ManageRecords
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
