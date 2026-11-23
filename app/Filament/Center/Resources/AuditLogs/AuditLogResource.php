<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\AuditLogs;

use App\Filament\Center\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Central\Resources\AuditLogs\AuditLogResource as NationalAuditLogResource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Office audit trail (audit.view_office): only entries of the tenant in context.
 * audit_logs has no tenant global scope (platform entries have no tenant), so the
 * filter is applied here instead of through Filament's ownership relationship.
 */
final class AuditLogResource extends NationalAuditLogResource
{
    protected static bool $isScopedToTenant = false;

    protected static bool $showsTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', app(TenantContext::class)->requireId());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
