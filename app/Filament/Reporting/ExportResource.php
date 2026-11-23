<?php

declare(strict_types=1);

namespace App\Filament\Reporting;

use App\Domain\Reporting\Actions\Exports;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ExportType;
use App\Domain\Reporting\Models\ExportJob;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * The user's own exports, in an office or in Central (11 §11.5).
 */
abstract class ExportResource extends Resource
{
    protected static ?string $model = ExportJob::class;

    protected static ?string $slug = 'exports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 50;

    /** @return list<ExportType> */
    abstract protected static function types(): array;

    public static function getModelLabel(): string
    {
        return __('reports.exports.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reports.exports.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('requested_by', auth()->id() ?? 0);
    }

    public static function requestAction(): Action
    {
        return Action::make('request')->label(__('reports.actions.request'))
            ->schema([
                Select::make('export_type')->label(__('reports.fields.export_type'))->required()->live()
                    ->options(collect(static::types())->mapWithKeys(fn (ExportType $type): array => [$type->value => $type->label()])->all()),
                DatePicker::make('from')->label(__('reports.fields.from'))
                    ->visible(fn (Get $get): bool => $get('export_type') === ExportType::ShahadahStatistics->value),
                DatePicker::make('to')->label(__('reports.fields.to'))
                    ->visible(fn (Get $get): bool => $get('export_type') === ExportType::ShahadahStatistics->value),
                Textarea::make('reason')->label(__('reports.fields.reason'))->maxLength(500)
                    ->required(fn (Get $get): bool => ExportType::tryFrom((string) $get('export_type'))?->isSensitive() ?? false),
            ])
            ->action(function (array $data, Action $action): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    abort(403);
                }

                try {
                    [, $password] = app(Exports::class)->request(
                        ExportType::from((string) $data['export_type']),
                        array_filter(['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]),
                        $data['reason'] ?? null,
                        $user,
                    );
                } catch (RuleViolation $violation) {
                    Notification::make()->danger()->title($violation->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->persistent()->title(__('reports.notifications.requested'))
                    ->body(__('reports.notifications.password', ['password' => $password]))->send();
            });
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('app.display_timezone');

        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('created_at')->label(__('reports.fields.requested_at'))->dateTime('Y-m-d H:i', $timezone),
                TextColumn::make('export_type')->label(__('reports.fields.export_type'))->formatStateUsing(fn (ExportType $state): string => $state->label()),
                TextColumn::make('status')->label(__('reports.fields.status'))->badge()->formatStateUsing(fn (ExportStatus $state): string => $state->label())
                    ->color(fn (ExportStatus $state): string => match ($state) {
                        ExportStatus::Ready => 'success',
                        ExportStatus::Failed, ExportStatus::Expired => 'danger',
                        ExportStatus::Queued, ExportStatus::Processing => 'warning',
                    }),
                TextColumn::make('row_count')->label(__('reports.fields.rows'))->placeholder('—'),
                TextColumn::make('expires_at')->label(__('reports.fields.expires_at'))->dateTime('Y-m-d H:i', $timezone)->placeholder('—'),
                TextColumn::make('download_count')->label(__('reports.fields.downloads')),
            ])
            ->headerActions([static::requestAction()])
            ->recordActions([
                Action::make('download')->label(__('reports.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ExportJob $record): bool => $record->isDownloadable())
                    ->action(function (ExportJob $record, Component $livewire): void {
                        $user = auth()->user();
                        $livewire->redirect(app(Exports::class)->downloadUrl($record, $user instanceof User ? $user : abort(403)));
                    }),
            ]);
    }
}
