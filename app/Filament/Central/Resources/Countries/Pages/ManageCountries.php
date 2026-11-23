<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Countries\Pages;

use App\Filament\Central\Resources\Countries\CountryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageCountries extends ManageRecords
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
