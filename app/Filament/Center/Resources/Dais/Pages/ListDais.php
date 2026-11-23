<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Dais\Pages;

use App\Filament\Center\Resources\Dais\DaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListDais extends ListRecords
{
    protected static string $resource = DaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
