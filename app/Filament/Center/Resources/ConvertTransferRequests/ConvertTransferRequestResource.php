<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ConvertTransferRequests;

use App\Domain\Dai\Services\DaiDirectory;
use App\Domain\Transfers\Actions\AcceptConvertTransfer;
use App\Domain\Transfers\Actions\CancelConvertTransfer;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\ConvertTransferRequest;
use App\Domain\Transfers\Services\TransferLabels;
use App\Filament\Center\Resources\ConvertTransferRequests\Pages\ManageConvertTransferRequests;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Incoming and outgoing convert transfers of this office (TransferPartyScope).
 */
final class ConvertTransferRequestResource extends Resource
{
    protected static ?string $model = ConvertTransferRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 24;

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('transfers.convert_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transfers.convert_plural');
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
        $timezone = (string) config('app.display_timezone');

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('direction')->label(__('transfers.fields.direction'))->badge()
                    ->state(fn (ConvertTransferRequest $record): string => app(TransferLabels::class)->isIncoming($record)
                        ? __('transfers.directions.incoming') : __('transfers.directions.outgoing')),
                TextColumn::make('convert_name')->label(__('transfers.fields.convert'))
                    ->state(fn (ConvertTransferRequest $record): string => self::user() === null ? '—'
                        : app(TransferLabels::class)->convertName($record, self::user())),
                TextColumn::make('fromTenant.code')->label(__('transfers.fields.from')),
                TextColumn::make('toTenant.code')->label(__('transfers.fields.to')),
                TextColumn::make('status')->label(__('transfers.fields.status'))->badge()
                    ->formatStateUsing(fn (TransferStatus $state): string => $state->label())
                    ->color(fn (TransferStatus $state): string => $state->color()),
                TextColumn::make('reason')->label(__('transfers.fields.reason'))->limit(40)->tooltip(fn (ConvertTransferRequest $record): string => $record->reason),
                TextColumn::make('requested_at')->label(__('transfers.fields.requested_at'))->since(timezone: $timezone),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('transfers.fields.status'))
                    ->options(collect(TransferStatus::cases())->mapWithKeys(fn (TransferStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('startReview')->label(__('transfers.actions.start_review'))
                    ->authorize(fn (ConvertTransferRequest $record): bool => $record->status === TransferStatus::Pending && self::userCan('review', $record))
                    ->action(self::run(fn (ConvertTransferRequest $record, array $data, User $user) => app(ReviewConvertTransfer::class)->startReview($record, $user))),
                Action::make('approve')->label(__('transfers.actions.approve'))->color('success')
                    ->authorize(fn (ConvertTransferRequest $record): bool => self::userCan('review', $record))
                    ->schema([Textarea::make('note')->label(__('transfers.fields.note'))->maxLength(1000)])
                    ->action(self::run(fn (ConvertTransferRequest $record, array $data, User $user) => app(ReviewConvertTransfer::class)->approve($record, $user, $data['note'] ?? null))),
                Action::make('reject')->label(__('transfers.actions.reject'))->color('danger')
                    ->authorize(fn (ConvertTransferRequest $record): bool => self::userCan('review', $record))
                    ->schema([Textarea::make('note')->label(__('transfers.fields.reason'))->required()->maxLength(1000)])
                    ->action(self::run(fn (ConvertTransferRequest $record, array $data, User $user) => app(ReviewConvertTransfer::class)->reject($record, $user, $data['note'] ?? null))),
                Action::make('accept')->label(__('transfers.actions.accept'))->color('success')
                    ->authorize(fn (ConvertTransferRequest $record): bool => self::userCan('accept', $record))
                    ->schema([Select::make('dai_id')->label(__('transfers.fields.new_primary_dai'))->required()->searchable()
                        ->options(fn (): array => app(DaiDirectory::class)->activeOptions())])
                    ->action(self::run(fn (ConvertTransferRequest $record, array $data, User $user) => app(AcceptConvertTransfer::class)->handle($record, (int) $data['dai_id'], $user))),
                Action::make('cancel')->label(__('transfers.actions.cancel'))->color('gray')
                    ->authorize(fn (ConvertTransferRequest $record): bool => self::userCan('cancel', $record))
                    ->requiresConfirmation()
                    ->action(self::run(fn (ConvertTransferRequest $record, array $data, User $user) => app(CancelConvertTransfer::class)->handle($record, $user, null))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageConvertTransferRequests::route('/'),
        ];
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function userCan(string $ability, ConvertTransferRequest $record): bool
    {
        return self::user()?->can($ability, $record) ?? false;
    }

    /**
     * @param  Closure(ConvertTransferRequest, array<string, mixed>, User): mixed  $handler
     * @return Closure(ConvertTransferRequest, array<string, mixed>, Action): void
     */
    private static function run(Closure $handler): Closure
    {
        return function (ConvertTransferRequest $record, array $data, Action $action) use ($handler): void {
            $user = self::user();

            try {
                if ($user !== null) {
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
