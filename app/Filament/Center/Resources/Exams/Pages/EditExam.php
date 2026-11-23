<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exams\Pages;

use App\Domain\Examinations\Models\Exam;
use App\Filament\Center\Resources\Exams\ExamResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Exam $record
 */
final class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['component_rows'] = $this->record->components()->get(['component_code', 'name', 'max_score', 'weight'])->toArray();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return ExamResource::save($this->record, $data);
    }
}
