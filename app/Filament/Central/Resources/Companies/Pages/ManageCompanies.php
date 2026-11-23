<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Companies\Pages;

use App\Filament\Central\Resources\Companies\CompanyResource;
use App\Filament\Schemas\CompanySchema;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageCompanies extends ManageRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => CompanySchema::save(null, $data)),
        ];
    }
}
