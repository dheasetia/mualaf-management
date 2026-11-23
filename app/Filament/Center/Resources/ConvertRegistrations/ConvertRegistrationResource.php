<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ConvertRegistrations;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Registration\Actions\VerifyShahadah;
use App\Domain\Registration\Enums\RegistrationStatus;
use App\Domain\Registration\Models\ConvertRegistration;
use App\Filament\Center\Resources\ConvertRegistrations\Pages\ManageConvertRegistrations;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrations made by this office, with the Shahadah Verifier's queue.
 */
final class ConvertRegistrationResource extends Resource
{
    protected static ?string $model = ConvertRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 22;

    /** Scoped below by tenant_id: the office that registered. */
    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('registration.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('registration.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->hasPermission('shahadah.verify') ?? false)) {
            return null;
        }

        $pending = self::getEloquentQuery()->where('status', RegistrationStatus::PendingVerification)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', app(TenantContext::class)->requireId())
            ->with(['convert', 'registeredBy', 'shahadah.witnesses']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('registration_no')->label(__('registration.fields.registration_no'))->searchable()->fontFamily('mono'),
                TextColumn::make('convert_name')->label(__('registration.fields.convert'))
                    ->state(fn (ConvertRegistration $record): string => self::convertName($record->convert)),
                TextColumn::make('registration_date')->label(__('registration.fields.registration_date'))->date('Y-m-d')->sortable(),
                TextColumn::make('status')->label(__('registration.fields.status'))->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state === RegistrationStatus::Verified ? 'success' : 'warning'),
                TextColumn::make('registeredBy.name')->label(__('registration.fields.registered_by')),
                TextColumn::make('witnesses')->label(__('registration.fields.witnesses'))->listWithLineBreaks()
                    ->state(fn (ConvertRegistration $record): array => $record->shahadah?->witnesses->pluck('name')->all() ?? []),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('registration.fields.status'))
                    ->options(collect(RegistrationStatus::cases())->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(fn (): ?string => (auth()->user()?->hasPermission('shahadah.verify') ?? false) ? RegistrationStatus::PendingVerification->value : null),
            ])
            ->recordActions([
                Action::make('verify')->label(__('registration.actions.verify'))->icon('heroicon-o-check-badge')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ConvertRegistration $record): string => __('registration.fields.witnesses').': '
                        .($record->shahadah?->witnesses->pluck('name')->join('، ') ?? ''))
                    ->authorize(fn (ConvertRegistration $record): bool => auth()->user()?->can('verify', $record) ?? false)
                    ->action(function (ConvertRegistration $record, Action $action): void {
                        $user = auth()->user();

                        try {
                            if ($user instanceof User) {
                                app(VerifyShahadah::class)->handle($record, $user);
                            }
                        } catch (RuleViolation $violation) {
                            Notification::make()->danger()->title($violation->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title(__('registration.notifications.verified'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageConvertRegistrations::route('/'),
        ];
    }

    private static function convertName(Convert $convert): string
    {
        $user = auth()->user();

        return $user instanceof User ? app(ConvertAccess::class)->displayName($user, $convert) : $convert->alias();
    }
}
