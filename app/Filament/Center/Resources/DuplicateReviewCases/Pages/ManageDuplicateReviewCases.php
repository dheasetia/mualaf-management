<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DuplicateReviewCases\Pages;

use App\Filament\Center\Resources\DuplicateReviewCases\DuplicateReviewCaseResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageDuplicateReviewCases extends ManageRecords
{
    protected static string $resource = DuplicateReviewCaseResource::class;
}
