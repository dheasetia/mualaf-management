<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\AuditLogs\Pages;

use App\Filament\Central\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageAuditLogs extends ManageRecords
{
    protected static string $resource = AuditLogResource::class;
}
