<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\Pages;

use App\Domain\Guidance\Models\GuidanceClass;
use App\Filament\Center\Resources\GuidanceClasses\GuidanceClassResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property GuidanceClass $record
 */
final class EditGuidanceClass extends EditRecord
{
    protected static string $resource = GuidanceClassResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['schedule_rows'] = $this->record->schedules()->get(['day_of_week', 'start_time', 'end_time'])->toArray();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return GuidanceClassResource::save($this->record, $data);
    }
}
