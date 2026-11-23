<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\CertificateTemplates\Pages;

use App\Filament\Central\Resources\CertificateTemplates\CertificateTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageCertificateTemplates extends ManageRecords
{
    protected static string $resource = CertificateTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => CertificateTemplateResource::save(null, $data)),
        ];
    }
}
