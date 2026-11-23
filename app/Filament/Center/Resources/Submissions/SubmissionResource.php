<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Submissions;

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Administration\Models\SubmissionEvent;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Filament\Center\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Center\Resources\Submissions\Pages\ViewSubmission;
use App\Models\User;
use App\Support\Localization\LocalizedName;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Administrative submissions of the office (10 §10.1).
 */
final class SubmissionResource extends Resource
{
    protected static ?string $model = Submission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $recordTitleAttribute = 'reference_no';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('submissions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('submissions.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('submissions.navigation_group');
    }

    public static function convertName(int $convertId): string
    {
        $user = auth()->user();
        $convert = Convert::query()->find($convertId);

        return $user instanceof User && $convert instanceof Convert ? app(ConvertAccess::class)->displayName($user, $convert) : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        $timezone = (string) config('app.display_timezone');

        return $schema->columns(1)->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('reference_no')->label(__('submissions.fields.reference_no')),
                TextEntry::make('status')->label(__('submissions.fields.status'))->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state): string => $state->label())->color(fn (SubmissionStatus $state): string => $state->color()),
                TextEntry::make('type.name_ar')->formatStateUsing(fn (Submission $record): ?string => LocalizedName::of($record->type))->label(__('submissions.fields.type')),
                TextEntry::make('convert')->label(__('submissions.fields.convert'))->state(fn (Submission $record): string => self::convertName($record->convert_id)),
                TextEntry::make('submitted_at')->label(__('submissions.fields.submitted_at'))->dateTime('Y-m-d H:i', $timezone)->placeholder('—'),
                TextEntry::make('due_at')->label(__('submissions.fields.due_at'))->date('Y-m-d', $timezone)->placeholder('—'),
                TextEntry::make('notes')->label(__('submissions.fields.notes'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('correction_note')->label(__('submissions.fields.correction_note'))->visible(fn (Submission $record): bool => $record->correction_note !== null)->columnSpanFull(),
            ]),
            Section::make(__('submissions.fields.events'))->collapsed()->schema([
                RepeatableEntry::make('events')->hiddenLabel()->columns(3)->schema([
                    TextEntry::make('created_at')->label(__('submissions.fields.when'))->dateTime('Y-m-d H:i', $timezone),
                    TextEntry::make('event_type')->label(__('submissions.fields.event'))
                        ->formatStateUsing(fn (SubmissionEvent $record): string => (string) __('submissions.events.'.$record->event_type)
                            .($record->to_status === null ? '' : ' → '.SubmissionStatus::from($record->to_status)->label())),
                    TextEntry::make('reason')->label(__('submissions.fields.reason'))->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('type'))
            ->columns([
                TextColumn::make('reference_no')->label(__('submissions.fields.reference_no'))->searchable()->fontFamily('mono'),
                TextColumn::make('type.name_ar')->formatStateUsing(fn (Submission $record): ?string => LocalizedName::of($record->type))->label(__('submissions.fields.type')),
                TextColumn::make('convert')->label(__('submissions.fields.convert'))->state(fn (Submission $record): string => self::convertName($record->convert_id)),
                TextColumn::make('status')->label(__('submissions.fields.status'))->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state): string => $state->label())->color(fn (SubmissionStatus $state): string => $state->color()),
                TextColumn::make('due_at')->label(__('submissions.fields.due_at'))->date('Y-m-d', (string) config('app.display_timezone'))->placeholder('—')
                    ->color(fn (Submission $record): string => $record->due_at !== null && $record->due_at->isPast() && ! in_array($record->status, [SubmissionStatus::Completed, SubmissionStatus::Rejected, SubmissionStatus::Cancelled], true) ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('submissions.fields.status'))
                    ->options(collect(SubmissionStatus::cases())->mapWithKeys(fn (SubmissionStatus $status): array => [$status->value => $status->label()])->all()),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmissions::route('/'),
            'view' => ViewSubmission::route('/{record}'),
        ];
    }
}
