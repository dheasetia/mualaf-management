<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Actions\DocumentLibrary;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\VerificationStatus;
use App\Domain\Documents\Models\Document;
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
 * Files of a convert: listed with the full profile and documents.view,
 * uploaded, verified and downloaded only through DocumentLibrary.
 */
final class DocumentsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('documents.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Convert && (auth()->user()?->can('viewDocuments', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();
        $can = fn (string $ability): bool => $owner instanceof Convert && (auth()->user()?->can($ability, $owner) ?? false);
        $library = fn (): DocumentLibrary => app(DocumentLibrary::class);

        return $table
            ->columns([
                TextColumn::make('document_type')->label(__('documents.fields.type'))->formatStateUsing(fn (DocumentType $state): string => $state->label()),
                TextColumn::make('original_filename')->label(__('documents.fields.filename'))->limit(40),
                TextColumn::make('current_version')->label(__('documents.fields.version')),
                TextColumn::make('verification_status')->label(__('documents.fields.status'))->badge()
                    ->formatStateUsing(fn (VerificationStatus $state): string => $state->label())
                    ->color(fn (VerificationStatus $state): string => match ($state) {
                        VerificationStatus::Verified => 'success',
                        VerificationStatus::Rejected => 'danger',
                        VerificationStatus::Pending => 'warning',
                    }),
                TextColumn::make('updated_at')->label(__('documents.fields.uploaded_at'))->dateTime('Y-m-d H:i', timezone: (string) config('app.display_timezone')),
            ])
            ->headerActions([
                Action::make('upload')->label(__('documents.actions.upload'))->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => $can('uploadDocuments'))
                    ->schema([
                        Select::make('document_type')->label(__('documents.fields.type'))->required()
                            ->options(collect(DocumentType::cases())->mapWithKeys(fn (DocumentType $type): array => [$type->value => $type->label()])->all()),
                        self::fileField(),
                    ])
                    ->action(self::domainAction(function (mixed $record, array $data, User $user) use ($owner, $library): void {
                        $file = self::uploaded($data);
                        $library()->upload($owner instanceof Convert ? $owner : abort(404), DocumentType::from((string) $data['document_type']), $file->getRealPath(), $file->getClientOriginalName(), $user);
                    }, 'documents.notifications.saved')),
            ])
            ->recordActions([
                Action::make('download')->label(__('documents.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Document $record): void {
                        $user = auth()->user();
                        $this->redirect(app(DocumentLibrary::class)->downloadUrl($record, $user instanceof User ? $user : abort(403)));
                    }),
                Action::make('newVersion')->label(__('documents.actions.new_version'))->color('gray')
                    ->visible(fn (): bool => $can('uploadDocuments'))
                    ->schema([self::fileField()])
                    ->action(self::domainAction(function (Document $record, array $data, User $user) use ($library): void {
                        $file = self::uploaded($data);
                        $library()->addVersion($record, $file->getRealPath(), $file->getClientOriginalName(), $user);
                    }, 'documents.notifications.saved')),
                Action::make('verify')->label(__('documents.actions.verify'))->color('success')->requiresConfirmation()
                    ->visible(fn (Document $record): bool => $record->verification_status === VerificationStatus::Pending && $can('verifyDocuments'))
                    ->action(self::domainAction(fn (Document $record, array $data, User $user) => $library()->verify($record, $user), 'documents.notifications.saved')),
                Action::make('reject')->label(__('documents.actions.reject'))->color('danger')
                    ->visible(fn (Document $record): bool => $record->verification_status === VerificationStatus::Pending && $can('verifyDocuments'))
                    ->schema([Textarea::make('reason')->label(__('documents.fields.reason'))->required()->maxLength(500)])
                    ->action(self::domainAction(fn (Document $record, array $data, User $user) => $library()->reject($record, $user, $data['reason'] ?? null), 'documents.notifications.saved')),
            ]);
    }

    private static function fileField(): FileUpload
    {
        return FileUpload::make('file')->label(__('documents.fields.file'))->required()->storeFiles(false)
            ->maxSize((int) (config('documents.max_bytes') / 1024))
            ->acceptedFileTypes(array_keys((array) config('documents.allowed_mime_types')))
            ->helperText(__('documents.help.file'));
    }

    /** @param  array<string, mixed>  $data */
    private static function uploaded(array $data): TemporaryUploadedFile
    {
        $file = $data['file'] ?? null;

        return $file instanceof TemporaryUploadedFile ? $file : abort(422);
    }
}
