<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\DocumentTemplates\Pages;

use App\Filament\Central\Resources\DocumentTemplates\DocumentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDocumentTemplatesPage extends ManageRecords
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => DocumentTemplateResource::save(null, $data)),
        ];
    }
}
