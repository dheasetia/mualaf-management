<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\Pages;

use App\Filament\Center\Resources\PilgrimagePrograms\PilgrimageProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPilgrimagePrograms extends ListRecords
{
    protected static string $resource = PilgrimageProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
