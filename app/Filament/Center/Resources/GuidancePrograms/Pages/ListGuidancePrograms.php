<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidancePrograms\Pages;

use App\Filament\Center\Resources\GuidancePrograms\GuidanceProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGuidancePrograms extends ListRecords
{
    protected static string $resource = GuidanceProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->hasPermission('guidance.programs.manage') ?? false)
                ->using(fn (array $data) => GuidanceProgramResource::save(null, $data)),
        ];
    }
}
