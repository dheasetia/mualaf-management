<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ProspectTransferRequests\Pages;

use App\Filament\Center\Resources\ProspectTransferRequests\ProspectTransferRequestResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProspectTransferRequests extends ManageRecords
{
    protected static string $resource = ProspectTransferRequestResource::class;
}
