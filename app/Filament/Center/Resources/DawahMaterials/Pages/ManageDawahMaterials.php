<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahMaterials\Pages;

use App\Filament\Center\Resources\DawahMaterials\DawahMaterialResource;
use App\Filament\Schemas\DawahMaterialSchema;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDawahMaterials extends ManageRecords
{
    protected static string $resource = DawahMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => DawahMaterialSchema::save(null, $data)),
        ];
    }
}
