<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ActivityTransport\Pages;

use App\Filament\Center\Resources\ActivityTransport\ActivityTransportRequestResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageActivityTransport extends ManageRecords
{
    protected static string $resource = ActivityTransportRequestResource::class;
}
