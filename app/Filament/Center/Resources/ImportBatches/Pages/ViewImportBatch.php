<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ImportBatches\Pages;

use App\Domain\Registration\Actions\HistoricalImports;
use App\Domain\Registration\Enums\ImportBatchStatus;
use App\Domain\Registration\Models\ImportBatch;
use App\Filament\Center\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * Start, retry or cancel an import; HistoricalImports decides.
 *
 * @property ImportBatch $record
 */
final class ViewImportBatch extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        $imports = fn (): HistoricalImports => app(HistoricalImports::class);
        $at = fn (ImportBatchStatus $status): bool => $this->record->status === $status;
        $refresh = fn (): mixed => $this->record->refresh();

        return [
            Action::make('start')->label(__('imports.actions.start'))->color('primary')->requiresConfirmation()
                ->modalDescription(__('imports.confirm.start'))
                ->visible(fn (): bool => $at(ImportBatchStatus::Validated) && $this->record->total_rows > $this->record->failed_rows)
                ->action(self::domainAction(function (mixed $record, array $data, User $actor) use ($imports, $refresh): void {
                    $imports()->start($this->record, $actor);
                    $refresh();
                }, 'imports.notifications.started')),
            Action::make('retry')->label(__('imports.actions.retry'))->color('warning')->requiresConfirmation()
                ->modalDescription(__('imports.confirm.retry'))
                ->visible(fn (): bool => $at(ImportBatchStatus::Completed) && $this->record->duplicate_rows > 0)
                ->action(self::domainAction(function (mixed $record, array $data, User $actor) use ($imports, $refresh): void {
                    $imports()->start($this->record, $actor);
                    $refresh();
                }, 'imports.notifications.started')),
            Action::make('cancel')->label(__('imports.actions.cancel'))->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => $at(ImportBatchStatus::Validated))
                ->action(self::domainAction(function (mixed $record, array $data, User $actor) use ($imports, $refresh): void {
                    $imports()->cancel($this->record, $actor);
                    $refresh();
                }, 'imports.notifications.cancelled')),
        ];
    }
}
