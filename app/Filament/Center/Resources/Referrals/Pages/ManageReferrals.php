<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Referrals\Pages;

use App\Filament\Center\Resources\Referrals\ReferralResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageReferrals extends ManageRecords
{
    protected static string $resource = ReferralResource::class;
}
