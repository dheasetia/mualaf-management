<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\DaiActivityTypes\Pages;

use App\Filament\Central\Resources\DaiActivityTypes\DaiActivityTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDaiActivityTypes extends ManageRecords
{
    protected static string $resource = DaiActivityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
