<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Religions\Pages;

use App\Filament\Central\Resources\Religions\ReligionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageReligions extends ManageRecords
{
    protected static string $resource = ReligionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
