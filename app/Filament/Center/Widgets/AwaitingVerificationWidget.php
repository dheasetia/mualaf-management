<?php

declare(strict_types=1);

namespace App\Filament\Center\Widgets;

use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Filament\Center\Resources\ConvertRegistrations\ConvertRegistrationResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shahadah registrations this user may verify: pending, in this office, and
 * not recorded or corrected by them (segregation of duties, as in the policy).
 */
final class AwaitingVerificationWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('shahadah.verify') ?? false;
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $userId = auth()->id();

        return $table
            ->heading(__('dashboard.awaiting_verification.heading'))
            ->description(__('dashboard.awaiting_verification.description'))
            ->query(fn () => ConvertRegistration::query()
                ->where('tenant_id', app(TenantContext::class)->requireId())
                ->where('status', RegistrationStatus::PendingVerification)
                ->where('registered_by', '!=', $userId)
                ->where(fn (Builder $query) => $query->whereNull('corrected_by')->orWhere('corrected_by', '!=', $userId))
                ->with(['convert', 'registeredBy']))
            ->defaultSort('registration_date')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('dashboard.awaiting_verification.empty'))
            ->recordUrl(fn (): string => ConvertRegistrationResource::getUrl())
            ->columns([
                TextColumn::make('registration_no')->label(__('registration.fields.registration_no')),
                TextColumn::make('registration_date')->label(__('registration.fields.registration_date'))->date('Y-m-d', $timezone),
                TextColumn::make('convert_name')
                    ->label(__('followup.fields.convert'))
                    ->state(fn (ConvertRegistration $record): string => ($viewer = auth()->user()) instanceof User
                        ? app(ConvertAccess::class)->displayName($viewer, $record->convert) : '—'),
                TextColumn::make('registeredBy.name')->label(__('dashboard.awaiting_verification.registered_by')),
            ]);
    }
}
