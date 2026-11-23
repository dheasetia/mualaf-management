<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Tenancy\Models\Tenant;

/**
 * Where an alert points in the office panel; implemented by the UI layer so
 * the domain does not depend on it.
 */
interface OfficeLinks
{
    public function shahadahRegistrations(Tenant $tenant): string;

    public function certificates(Tenant $tenant): string;

    public function transfers(Tenant $tenant): string;

    public function followUpSchedule(Tenant $tenant): string;

    public function convert(Tenant $tenant, int $convertId): string;
}
