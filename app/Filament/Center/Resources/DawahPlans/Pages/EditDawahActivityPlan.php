<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\Pages;

use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\DawahActivityPlanResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property DawahActivityPlan $record
 */
final class EditDawahActivityPlan extends EditRecord
{
    protected static string $resource = DawahActivityPlanResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DawahActivityPlanResource::save($this->record, $data);
    }
}
