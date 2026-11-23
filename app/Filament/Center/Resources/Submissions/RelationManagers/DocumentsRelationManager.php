<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Submissions\RelationManagers;

use App\Domain\Administration\Actions\SubmissionWorkflow;
use App\Domain\Administration\Enums\DocumentCheck;
use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionDocument;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Files of a submission, per requirement, with the verifier's check.
 */
final class DocumentsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('submissions.documents.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $submission = $this->getOwnerRecord();
        $current = fn (): ?Submission => $submission instanceof Submission ? Submission::query()->with('type')->find($submission->id) : null;
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
        $workflow = fn (): SubmissionWorkflow => app(SubmissionWorkflow::class);
        $verifying = fn (): bool => $current()?->status === SubmissionStatus::UnderVerification && $can('submissions.verify');

        return $table
            ->columns([
                TextColumn::make('requirement_code')->label(__('submissions.fields.requirement'))
                    ->formatStateUsing(fn (string $state): string => $current()?->type->requirementOptions()[$state] ?? $state),
                TextColumn::make('verification_status')->label(__('submissions.fields.check'))->badge()
                    ->formatStateUsing(fn (DocumentCheck $state): string => $state->label())
                    ->color(fn (DocumentCheck $state): string => match ($state) {
                        DocumentCheck::Verified => 'success',
                        DocumentCheck::Rejected => 'danger',
                        DocumentCheck::Pending => 'gray',
                    }),
                TextColumn::make('rejection_note')->label(__('submissions.fields.note'))->placeholder('—')->limit(50),
            ])
            ->headerActions([
                Action::make('attach')->label(__('submissions.actions.attach'))->icon('heroicon-o-paper-clip')
                    ->visible(fn (): bool => ($current()?->status->acceptsDocuments() ?? false) && $can('submissions.create'))
                    ->schema([
                        Select::make('requirement_code')->label(__('submissions.fields.requirement'))->required()
                            ->options(fn (): array => $current()?->type->requirementOptions() ?? []),
                        FileUpload::make('file')->label(__('submissions.fields.file'))->required()->storeFiles(false)
                            ->maxSize((int) (config('documents.max_bytes') / 1024))
                            ->acceptedFileTypes(array_keys((array) config('documents.allowed_mime_types'))),
                    ])
                    ->action(self::domainAction(function (mixed $record, array $data, User $user) use ($workflow, $submission): void {
                        $file = $data['file'] ?? null;

                        if (! $file instanceof TemporaryUploadedFile || ! $submission instanceof Submission) {
                            abort(422);
                        }

                        $workflow()->attach($submission, (string) $data['requirement_code'], $file->getRealPath(), $file->getClientOriginalName(), $user);
                    }, 'submissions.notifications.saved')),
            ])
            ->recordActions([
                Action::make('download')->label(__('submissions.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->action(function (SubmissionDocument $record) use ($workflow): void {
                        $user = auth()->user();
                        $this->redirect($workflow()->documentUrl($record, $user instanceof User ? $user : abort(403)));
                    }),
                Action::make('verifyDocument')->label(__('submissions.actions.verify_document'))->color('success')->visible($verifying)
                    ->action(self::domainAction(fn (SubmissionDocument $record, array $data, User $user) => $workflow()->checkDocument($record, DocumentCheck::Verified, null, $user), 'submissions.notifications.saved')),
                Action::make('rejectDocument')->label(__('submissions.actions.reject_document'))->color('danger')->visible($verifying)
                    ->schema([Textarea::make('note')->label(__('submissions.fields.note'))->required()->maxLength(1000)])
                    ->action(self::domainAction(fn (SubmissionDocument $record, array $data, User $user) => $workflow()->checkDocument($record, DocumentCheck::Rejected, $data['note'] ?? null, $user), 'submissions.notifications.saved')),
            ]);
    }
}
