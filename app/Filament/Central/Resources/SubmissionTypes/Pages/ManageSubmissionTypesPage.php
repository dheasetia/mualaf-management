<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\SubmissionTypes\Pages;

use App\Filament\Central\Resources\SubmissionTypes\SubmissionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageSubmissionTypesPage extends ManageRecords
{
    protected static string $resource = SubmissionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => SubmissionTypeResource::save(null, $data)),
        ];
    }
}
