<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\ImportBatches\Pages;

use App\Domain\Registration\Actions\HistoricalImports;
use App\Domain\Registration\Services\HistoricalImportRows;
use App\Filament\Center\Resources\ImportBatches\ImportBatchResource;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label(__('imports.actions.template'))->color('gray')->icon('heroicon-o-document-arrow-down')
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    $stream = fopen('php://output', 'w');

                    if ($stream !== false) {
                        fwrite($stream, "\xEF\xBB\xBF");
                        fputcsv($stream, array_keys(HistoricalImportRows::COLUMNS), escape: '');
                        fputcsv($stream, array_values(HistoricalImportRows::EXAMPLE), escape: '');
                        fclose($stream);
                    }
                }, 'convert-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            Action::make('upload')->label(__('imports.actions.upload'))->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('file')->label(__('imports.fields.file'))->required()->storeFiles(false)
                        ->maxSize((int) config('registration.import_max_kilobytes'))
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->helperText(__('imports.help.file')),
                ])
                ->action(function (array $data, Action $action): void {
                    $user = auth()->user();
                    $file = $data['file'] ?? null;

                    if (! $user instanceof User || ! $file instanceof TemporaryUploadedFile) {
                        abort(422);
                    }

                    try {
                        $batch = app(HistoricalImports::class)->upload($file->getRealPath(), $file->getClientOriginalName(), $user);
                    } catch (RuleViolation $violation) {
                        Notification::make()->danger()->title($violation->getMessage())->send();
                        $action->halt();

                        return;
                    } finally {
                        $file->delete();
                    }

                    Notification::make()->success()->title(__('imports.notifications.uploaded'))->send();
                    $this->redirect(ImportBatchResource::getUrl('view', ['record' => $batch]));
                }),
        ];
    }
}
