<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Languages\Pages;

use App\Filament\Central\Resources\Languages\LanguageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageLanguages extends ManageRecords
{
    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
