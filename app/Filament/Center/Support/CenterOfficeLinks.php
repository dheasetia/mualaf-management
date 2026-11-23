<?php

declare(strict_types=1);

namespace App\Filament\Center\Support;

use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Certificates\CertificateResource;
use App\Filament\Center\Resources\ConvertRegistrations\ConvertRegistrationResource;
use App\Filament\Center\Resources\Converts\ConvertResource;
use App\Filament\Center\Resources\ConvertTransferRequests\ConvertTransferRequestResource;
use App\Filament\Center\Resources\FollowUpSchedules\FollowUpScheduleResource;

final class CenterOfficeLinks implements OfficeLinks
{
    public function shahadahRegistrations(Tenant $tenant): string
    {
        return ConvertRegistrationResource::getUrl('index', panel: 'center', tenant: $tenant);
    }

    public function certificates(Tenant $tenant): string
    {
        return CertificateResource::getUrl('index', panel: 'center', tenant: $tenant);
    }

    public function transfers(Tenant $tenant): string
    {
        return ConvertTransferRequestResource::getUrl('index', panel: 'center', tenant: $tenant);
    }

    public function followUpSchedule(Tenant $tenant): string
    {
        return FollowUpScheduleResource::getUrl('index', panel: 'center', tenant: $tenant);
    }

    public function convert(Tenant $tenant, int $convertId): string
    {
        return ConvertResource::getUrl('view', ['record' => $convertId], panel: 'center', tenant: $tenant);
    }
}
