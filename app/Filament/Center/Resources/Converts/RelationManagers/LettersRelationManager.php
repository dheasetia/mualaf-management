<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Converts\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Actions\LetterWorkflow;
use App\Domain\Documents\Enums\GeneratedStatus;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Letters generated for a convert in this office (documents.generate).
 */
final class LettersRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'letters';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('documents.letters.plural');
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
        $canGenerate = fn (): bool => $owner instanceof Convert && (auth()->user()?->can('generateLetters', $owner) ?? false);
        $workflow = fn (): LetterWorkflow => app(LetterWorkflow::class);

        return $table
            ->columns([
                TextColumn::make('document_number')->label(__('documents.fields.number'))->fontFamily('mono'),
                TextColumn::make('template.document_type')->label(__('documents.fields.type'))->formatStateUsing(fn (LetterType $state): string => $state->label()),
                TextColumn::make('status')->label(__('documents.fields.status'))->badge()
                    ->formatStateUsing(fn (GeneratedStatus $state): string => $state->label())
                    ->color(fn (GeneratedStatus $state): string => $state === GeneratedStatus::Issued ? 'success' : 'gray'),
                TextColumn::make('pdf')->label(__('documents.fields.pdf'))
                    ->state(fn (GeneratedDocument $record): string => (string) ($record->hasPdf() ? __('documents.pdf.ready') : __('documents.pdf.processing'))),
                TextColumn::make('issued_at')->label(__('documents.fields.issued_on'))->date('Y-m-d', timezone: (string) config('app.display_timezone')),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('template'))
            ->headerActions([
                Action::make('generate')->label(__('documents.actions.generate'))->icon('heroicon-o-document-plus')
                    ->visible($canGenerate)
                    ->schema([
                        Select::make('document_type')->label(__('documents.fields.type'))->required()
                            ->options(collect(LetterWorkflow::CONVERT_LETTERS)->mapWithKeys(fn (LetterType $type): array => [$type->value => $type->label()])->all()),
                        TextInput::make('addressee')->label(__('documents.fields.addressee'))->required()->maxLength(255),
                        Textarea::make('body')->label(__('documents.fields.body'))->maxLength(2000),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => $workflow()->generate(
                        $owner instanceof Convert ? $owner : abort(404),
                        LetterType::from((string) $data['document_type']),
                        ['addressee' => $data['addressee'] ?? null, 'body' => $data['body'] ?? null],
                        $user,
                    ), 'documents.notifications.saved')),
            ])
            ->recordActions([
                Action::make('download')->label(__('documents.actions.download'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (GeneratedDocument $record): bool => $record->hasPdf())
                    ->action(function (GeneratedDocument $record): void {
                        $user = auth()->user();
                        $this->redirect(app(LetterWorkflow::class)->downloadUrl($record, $user instanceof User ? $user : abort(403)));
                    }),
                Action::make('cancel')->label(__('documents.actions.cancel'))->color('danger')
                    ->visible(fn (GeneratedDocument $record): bool => $record->status === GeneratedStatus::Issued && $canGenerate())
                    ->schema([Textarea::make('reason')->label(__('documents.fields.reason'))->required()->maxLength(500)])
                    ->action(self::domainAction(fn (GeneratedDocument $record, array $data, User $user) => $workflow()->cancel($record, $user, $data['reason'] ?? null), 'documents.notifications.saved')),
            ]);
    }
}
