<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\PilgrimagePrograms\Pages;

use App\Domain\Pilgrimage\Models\PilgrimageProgram;
use App\Filament\Center\Resources\PilgrimagePrograms\PilgrimageProgramResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PilgrimageProgram $record
 */
final class EditPilgrimageProgram extends EditRecord
{
    protected static string $resource = PilgrimageProgramResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->record->eligibility_rules];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return PilgrimageProgramResource::save($this->record, $data);
    }
}
