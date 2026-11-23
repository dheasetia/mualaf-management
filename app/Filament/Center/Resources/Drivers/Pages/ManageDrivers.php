<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Drivers\Pages;

use App\Filament\Center\Resources\Drivers\DriverResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDrivers extends ManageRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => DriverResource::save(null, $data)),
        ];
    }
}
