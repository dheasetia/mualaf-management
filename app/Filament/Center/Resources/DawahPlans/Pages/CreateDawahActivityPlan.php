<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\Pages;

use App\Filament\Center\Resources\DawahPlans\DawahActivityPlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateDawahActivityPlan extends CreateRecord
{
    protected static string $resource = DawahActivityPlanResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return DawahActivityPlanResource::save(null, $data);
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
