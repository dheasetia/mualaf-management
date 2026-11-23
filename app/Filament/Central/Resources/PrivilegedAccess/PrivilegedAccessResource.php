<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\PrivilegedAccess;

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Platform\Models\Country;
use App\Domain\Privileged\Actions\PrivilegedAccess;
use App\Domain\Privileged\Enums\PrivilegedScope;
use App\Domain\Privileged\Enums\PrivilegedStatus;
use App\Domain\Privileged\Models\PrivilegedAccessRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Central\Resources\PrivilegedAccess\Pages\ListPrivilegedAccess;
use App\Filament\Central\Resources\PrivilegedAccess\Pages\ViewPrivilegedAccess;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Break-glass requests and grants (04 §4.8).
 */
final class PrivilegedAccessResource extends Resource
{
    use RunsDomainActions;

    protected static ?string $model = PrivilegedAccessRequest::class;

    protected static ?string $slug = 'privileged-access';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 95;

    public static function getModelLabel(): string
    {
        return __('privileged.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('privileged.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('platform.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $user->hasPermission('privileged.approve') ? $query : $query->where('requested_by', $user->id);
    }

    public static function requestAction(): Action
    {
        return Action::make('request')->label(__('privileged.actions.request'))
            ->visible(fn (): bool => auth()->user()?->hasPermission('privileged.request') ?? false)
            ->schema([
                Select::make('identity_type')->label(__('privileged.fields.identity_type'))->required()->live()
                    ->options(collect(IdentityType::cases())->mapWithKeys(fn (IdentityType $type): array => [$type->value => $type->label()])->all()),
                Select::make('issuing_country_id')->label(__('converts.search.issuing_country'))->searchable()
                    ->options(fn (): array => Country::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                    ->hidden(fn (Get $get): bool => IdentityType::tryFrom((string) $get('identity_type'))?->issuedBySaudiArabia() ?? false),
                TextInput::make('identity_number')->label(__('privileged.fields.identity_number'))->required()->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                Textarea::make('reason')->label(__('privileged.fields.reason'))->required()->maxLength(2000),
                CheckboxList::make('scope')->label(__('privileged.fields.scope'))->required()->columns(2)
                    ->options(collect(PrivilegedScope::cases())->mapWithKeys(fn (PrivilegedScope $scope): array => [$scope->value => $scope->label()])->all()),
                Select::make('minutes')->label(__('privileged.fields.minutes'))->required()->default(60)
                    ->options([15 => '15', 30 => '30', 60 => '60', 120 => '120', 240 => '240', 480 => '480', 1440 => '1440']),
            ])
            ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(PrivilegedAccess::class)->request(
                new IdentifierData(IdentityType::from((string) $data['identity_type']), (string) $data['identity_number'], blank($data['issuing_country_id'] ?? null) ? null : (int) $data['issuing_country_id']),
                (string) $data['reason'],
                array_values(array_map('strval', (array) $data['scope'])),
                (int) $data['minutes'],
                $user,
            ), 'privileged.notifications.saved'));
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');
        $access = fn (): PrivilegedAccess => app(PrivilegedAccess::class);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;

        return $table
            ->defaultSort('id', 'desc')
            ->description(__('privileged.help'))
            ->columns([
                TextColumn::make('created_at')->label(__('privileged.fields.requested_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('requester')->label(__('privileged.fields.requested_by'))
                    ->state(fn (PrivilegedAccessRequest $record): string => (string) User::query()->whereKey($record->requested_by)->value('name')),
                TextColumn::make('office')->label(__('privileged.fields.office'))
                    ->state(fn (PrivilegedAccessRequest $record): string => (string) Tenant::query()->whereKey($record->tenant_id)->value('name_ar')),
                TextColumn::make('reason')->label(__('privileged.fields.reason'))->limit(50),
                TextColumn::make('scopes')->label(__('privileged.fields.scope'))
                    ->state(fn (PrivilegedAccessRequest $record): string => collect($record->requested_scope)->map(fn (string $scope): string => PrivilegedScope::tryFrom($scope)?->label() ?? $scope)->implode('، ')),
                TextColumn::make('status')->label(__('privileged.fields.status'))->badge()
                    ->formatStateUsing(fn (PrivilegedStatus $state): string => $state->label())->color(fn (PrivilegedStatus $state): string => $state->color()),
                TextColumn::make('expires_at')->label(__('privileged.fields.expires_at'))->dateTime('Y-m-d H:i', $timezone)->placeholder('—'),
            ])
            ->headerActions([self::requestAction()])
            ->recordActions([
                Action::make('open')->label(__('privileged.actions.open'))->icon('heroicon-o-eye')
                    ->visible(fn (PrivilegedAccessRequest $record): bool => $record->isActive() && $record->requested_by === auth()->id())
                    ->url(fn (PrivilegedAccessRequest $record): string => self::getUrl('view', ['record' => $record])),
                Action::make('approve')->label(__('privileged.actions.approve'))->color('success')
                    ->visible(fn (PrivilegedAccessRequest $record): bool => $record->status === PrivilegedStatus::Pending && $can('privileged.approve') && $record->requested_by !== auth()->id())
                    ->schema([Textarea::make('note')->label(__('privileged.fields.note'))->maxLength(1000)])
                    ->action(self::domainAction(fn (PrivilegedAccessRequest $record, array $data, User $user) => $access()->approve($record, $user, $data['note'] ?? null), 'privileged.notifications.saved')),
                Action::make('reject')->label(__('privileged.actions.reject'))->color('danger')
                    ->visible(fn (PrivilegedAccessRequest $record): bool => $record->status === PrivilegedStatus::Pending && $can('privileged.approve') && $record->requested_by !== auth()->id())
                    ->schema([Textarea::make('note')->label(__('privileged.fields.note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (PrivilegedAccessRequest $record, array $data, User $user) => $access()->reject($record, $user, $data['note'] ?? null), 'privileged.notifications.saved')),
                Action::make('revoke')->label(__('privileged.actions.revoke'))->color('danger')->requiresConfirmation()
                    ->visible(fn (PrivilegedAccessRequest $record): bool => $record->status === PrivilegedStatus::Active && ($record->requested_by === auth()->id() || $can('privileged.approve')))
                    ->action(self::domainAction(fn (PrivilegedAccessRequest $record, array $data, User $user) => $access()->revoke($record, $user), 'privileged.notifications.saved')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrivilegedAccess::route('/'),
            'view' => ViewPrivilegedAccess::route('/{record}'),
        ];
    }
}
