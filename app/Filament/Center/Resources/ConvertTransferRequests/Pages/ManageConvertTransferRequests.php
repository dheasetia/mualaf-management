<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ConvertTransferRequests\Pages;

use App\Filament\Center\Resources\ConvertTransferRequests\ConvertTransferRequestResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageConvertTransferRequests extends ManageRecords
{
    protected static string $resource = ConvertTransferRequestResource::class;
}
