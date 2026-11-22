<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Certificates\Pages;

use App\Filament\Center\Resources\Certificates\CertificateResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageCertificates extends ManageRecords
{
    protected static string $resource = CertificateResource::class;
}
