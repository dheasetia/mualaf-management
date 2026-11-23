<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\MaterialDistributions\Pages;

use App\Filament\Center\Resources\MaterialDistributions\MaterialDistributionResource;
use Filament\Resources\Pages\ListRecords;

final class ListMaterialDistributions extends ListRecords
{
    protected static string $resource = MaterialDistributionResource::class;
}
