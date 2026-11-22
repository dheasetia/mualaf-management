<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\Pages;

use App\Filament\Center\Resources\DawahPlans\DawahActivityPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListDawahActivityPlans extends ListRecords
{
    protected static string $resource = DawahActivityPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
