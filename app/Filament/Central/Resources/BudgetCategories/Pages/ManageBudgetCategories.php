<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\BudgetCategories\Pages;

use App\Filament\Central\Resources\BudgetCategories\BudgetCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageBudgetCategories extends ManageRecords
{
    protected static string $resource = BudgetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
