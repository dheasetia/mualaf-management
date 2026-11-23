<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Pickups\Pages;

use App\Filament\Center\Resources\Pickups\PickupResource;
use Filament\Resources\Pages\ManageRecords;

final class ManagePickups extends ManageRecords
{
    protected static string $resource = PickupResource::class;
}
