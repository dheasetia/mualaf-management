<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\FollowUpSchedules\Pages;

use App\Filament\Center\Resources\FollowUpSchedules\FollowUpScheduleResource;
use Filament\Resources\Pages\ListRecords;

final class ListFollowUpSchedules extends ListRecords
{
    protected static string $resource = FollowUpScheduleResource::class;
}
