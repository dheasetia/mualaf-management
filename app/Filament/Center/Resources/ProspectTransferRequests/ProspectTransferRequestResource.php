<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ProspectTransferRequests;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Transfers\Actions\ProspectTransfers;
use App\Domain\Transfers\Enums\ProspectTransferStatus;
use App\Domain\Transfers\Models\ProspectTransferRequest;
use App\Filament\Center\Resources\ProspectTransferRequests\Pages\ManageProspectTransferRequests;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use App\Support\Tenancy\TenantContext;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProspectTransferRequestResource extends Resource
{
    protected static ?string $model = ProspectTransferRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected static ?int $navigationSort = 25;

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('transfers.prospect_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transfers.prospect_plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['fromTenant', 'toTenant', 'requester']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('direction')->label(__('transfers.fields.direction'))->badge()
                    ->state(fn (ProspectTransferRequest $record): string => $record->to_tenant_id === app(TenantContext::class)->id()
                        ? __('transfers.directions.incoming') : __('transfers.directions.outgoing')),
                TextColumn::make('fromTenant.code')->label(__('transfers.fields.from')),
                TextColumn::make('toTenant.code')->label(__('transfers.fields.to')),
                TextColumn::make('status')->label(__('transfers.fields.status'))->badge()
                    ->formatStateUsing(fn (ProspectTransferStatus $state): string => $state->label()),
                TextColumn::make('reason')->label(__('transfers.fields.reason'))->limit(40),
                TextColumn::make('requester.name')->label(__('transfers.fields.requested_by')),
                TextColumn::make('created_at')->label(__('transfers.fields.requested_at'))->since(timezone: (string) config('app.display_timezone')),
            ])
            ->recordActions([
                Action::make('approve')->label(__('transfers.actions.accept'))->color('success')
                    ->authorize(fn (ProspectTransferRequest $record): bool => self::userCan('decide', $record))
                    ->schema([Select::make('dai_id')->label(__('transfers.fields.new_primary_dai'))->required()->searchable()
                        ->options(fn (): array => app(DaiDirectory::class)->activeOptions())])
                    ->action(self::run(fn (ProspectTransferRequest $record, array $data, User $user) => app(ProspectTransfers::class)->approve($record, (int) $data['dai_id'], $user))),
                Action::make('reject')->label(__('transfers.actions.reject'))->color('danger')
                    ->authorize(fn (ProspectTransferRequest $record): bool => self::userCan('decide', $record))
                    ->schema([Textarea::make('note')->label(__('transfers.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::run(fn (ProspectTransferRequest $record, array $data, User $user) => app(ProspectTransfers::class)->reject($record, $user, $data['note'] ?? null))),
                Action::make('cancel')->label(__('transfers.actions.cancel'))->color('gray')->requiresConfirmation()
                    ->authorize(fn (ProspectTransferRequest $record): bool => self::userCan('cancel', $record))
                    ->action(self::run(fn (ProspectTransferRequest $record, array $data, User $user) => app(ProspectTransfers::class)->cancel($record, $user))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProspectTransferRequests::route('/'),
        ];
    }

    private static function userCan(string $ability, ProspectTransferRequest $record): bool
    {
        return auth()->user()?->can($ability, $record) ?? false;
    }

    /**
     * @param  Closure(ProspectTransferRequest, array<string, mixed>, User): mixed  $handler
     * @return Closure(ProspectTransferRequest, array<string, mixed>, Action): void
     */
    private static function run(Closure $handler): Closure
    {
        return function (ProspectTransferRequest $record, array $data, Action $action) use ($handler): void {
            $user = auth()->user();

            try {
                if ($user instanceof User) {
                    $handler($record, $data, $user);
                }
            } catch (RuleViolation $violation) {
                Notification::make()->danger()->title($violation->getMessage())->send();
                $action->halt();
            }

            Notification::make()->success()->title(__('transfers.notifications.saved'))->send();
        };
    }
}
