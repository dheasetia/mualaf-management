<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ExternalOrganizations\Pages;

use App\Filament\Central\Resources\ExternalOrganizations\ExternalOrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageExternalOrganizations extends ManageRecords
{
    protected static string $resource = ExternalOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
