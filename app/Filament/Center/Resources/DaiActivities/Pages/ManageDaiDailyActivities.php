<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DaiActivities\Pages;

use App\Filament\Center\Resources\DaiActivities\DaiDailyActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDaiDailyActivities extends ManageRecords
{
    protected static string $resource = DaiDailyActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => DaiDailyActivityResource::save(null, $data)),
        ];
    }
}
