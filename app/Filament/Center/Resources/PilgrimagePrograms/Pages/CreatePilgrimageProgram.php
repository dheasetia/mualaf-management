<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\Pages;

use App\Filament\Center\Resources\PilgrimagePrograms\PilgrimageProgramResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePilgrimageProgram extends CreateRecord
{
    protected static string $resource = PilgrimageProgramResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return PilgrimageProgramResource::save(null, $data);
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
