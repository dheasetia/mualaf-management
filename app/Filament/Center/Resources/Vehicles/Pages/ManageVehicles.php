<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Vehicles\Pages;

use App\Filament\Center\Resources\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageVehicles extends ManageRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => VehicleResource::save(null, $data)),
        ];
    }
}
