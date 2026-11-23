<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ShahadahVoidRequests;

use App\Domain\Registration\Actions\ShahadahVoiding;
use App\Domain\Registration\Enums\VoidRequestStatus;
use App\Domain\Registration\Models\ShahadahVoidRequest;
use App\Filament\Central\Resources\ShahadahVoidRequests\Pages\ListShahadahVoidRequests;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Central decides offices' requests to void a shahadah registration (07
 * §7.1). Shows the registration number, office, reason and requester, not
 * the convert's profile.
 */
final class ShahadahVoidRequestResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = ShahadahVoidRequest::class;

    protected static ?string $slug = 'shahadah-void-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?int $navigationSort = 96;

    public static function getModelLabel(): string
    {
        return __('registration.void_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('registration.void_plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ShahadahVoidRequest::query()->where('status', VoidRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $voiding = fn (): ShahadahVoiding => app(ShahadahVoiding::class);

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['tenant', 'requestedBy']))
            ->columns([
                TextColumn::make('created_at')->label(__('registration.fields.requested_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('office')->label(__('registration.fields.office'))
                    ->state(fn (ShahadahVoidRequest $record): string => (string) LocalizedName::of($record->tenant)),
                TextColumn::make('registration_no')->label(__('registration.fields.registration_no'))->fontFamily('mono')
                    ->state(fn (ShahadahVoidRequest $record): string => self::registrationNumber($record)),
                TextColumn::make('reason')->label(__('registration.fields.void_reason'))->wrap()->limit(120),
                TextColumn::make('requestedBy.name')->label(__('registration.fields.requested_by')),
                TextColumn::make('status')->label(__('registration.fields.void_status'))->badge()
                    ->formatStateUsing(fn (VoidRequestStatus $state): string => $state->label())
                    ->color(fn (VoidRequestStatus $state): string => $state->color())
                    ->description(fn (ShahadahVoidRequest $record): ?string => $record->decision_note),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('registration.fields.void_status'))
                    ->options(collect(VoidRequestStatus::cases())->mapWithKeys(fn (VoidRequestStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(VoidRequestStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')->label(__('registration.actions.approve_void'))->icon('heroicon-o-no-symbol')->color('danger')
                    ->authorize(fn (ShahadahVoidRequest $record): bool => auth()->user()?->can('decide', $record) ?? false)
                    ->modalDescription(__('registration.help.void'))
                    ->schema([Textarea::make('note')->label(__('registration.fields.decision_note'))->maxLength(1000)])
                    ->action(self::domainAction(fn (ShahadahVoidRequest $record, array $data, User $user) => $voiding()->approve($record, $user, $data['note'] ?? null), 'registration.notifications.void_decided')),
                Action::make('reject')->label(__('registration.actions.reject_void'))->color('gray')
                    ->authorize(fn (ShahadahVoidRequest $record): bool => auth()->user()?->can('decide', $record) ?? false)
                    ->schema([Textarea::make('note')->label(__('registration.fields.decision_note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (ShahadahVoidRequest $record, array $data, User $user) => $voiding()->reject($record, $user, $data['note'] ?? null), 'registration.notifications.void_decided')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShahadahVoidRequests::route('/'),
        ];
    }

    /** The registration number only: Central does not read the office's convert data. */
    private static function registrationNumber(ShahadahVoidRequest $record): string
    {
        return (string) DB::table('convert_registrations')->where('id', $record->convert_registration_id)->value('registration_no');
    }
}
