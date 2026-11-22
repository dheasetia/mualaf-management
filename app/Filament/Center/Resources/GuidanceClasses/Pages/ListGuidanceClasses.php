<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\Pages;

use App\Filament\Center\Resources\GuidanceClasses\GuidanceClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGuidanceClasses extends ListRecords
{
    protected static string $resource = GuidanceClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->hasPermission('guidance.classes.manage') ?? false)
                ->using(fn (array $data) => GuidanceClassResource::save(null, $data)),
        ];
    }
}
