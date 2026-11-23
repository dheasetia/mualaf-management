<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Trips\Pages;

use App\Filament\Center\Resources\Trips\TripResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageTrips extends ManageRecords
{
    protected static string $resource = TripResource::class;
}
