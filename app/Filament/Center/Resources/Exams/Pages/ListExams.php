<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exams\Pages;

use App\Filament\Center\Resources\Exams\ExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->hasPermission('exams.manage') ?? false)
                ->using(fn (array $data) => ExamResource::save(null, $data)),
        ];
    }
}
