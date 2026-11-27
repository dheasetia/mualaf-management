<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DuplicateReviewCases;

use App\Domain\Registration\Actions\ResolveDuplicateCase;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Enums\ReviewStatus;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Domain\Registration\Services\DuplicateReview;
use App\Filament\Center\Resources\DuplicateReviewCases\Pages\ManageDuplicateReviewCases;
use App\Models\User;
use App\Support\Exceptions\RuleViolation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Head of Dawah review of registrations held as possible duplicates.
 */
final class DuplicateReviewCaseResource extends Resource
{
    protected static ?string $model = DuplicateReviewCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 23;

    public static function getModelLabel(): string
    {
        return __('duplicates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('duplicates.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('dai.navigation_group');
    }

    public static function getNavigationBadge(): ?string
    {
        $open = self::getEloquentQuery()->where('status', '!=', ReviewStatus::Resolved)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('creator');
    }

    public static function infolist(Schema $schema): Schema
    {
        $other = fn (DuplicateReviewCase $record): array => app(DuplicateReview::class)->possibleConvert($record);

        return $schema->components([
            Section::make(__('duplicates.model'))->columnSpanFull()->schema([
                Fieldset::make(__('duplicates.fields.candidate_name'))->schema([
                    TextEntry::make('candidate_name')->label(__('duplicates.fields.candidate_name'))
                        ->state(fn (DuplicateReviewCase $record): string => (string) ($record->match_details['candidate_name'] ?? '—')),
                    TextEntry::make('candidate_dob')->label(__('duplicates.fields.candidate_date_of_birth'))
                        ->state(fn (DuplicateReviewCase $record): string => (string) ($record->match_details['candidate_date_of_birth'] ?? '—')),
                    TextEntry::make('match_reason')->label(__('duplicates.fields.match_reason'))
                        ->formatStateUsing(fn (MatchReason $state): string => $state->label()),
                    TextEntry::make('match_score')->label(__('duplicates.fields.match_score')),
                ]),
                Fieldset::make(__('duplicates.fields.possible_convert'))->schema([
                    TextEntry::make('other_office')->label(__('duplicates.fields.office'))->state(fn (DuplicateReviewCase $record): string => $other($record)['office']),
                    TextEntry::make('other_name')->label(__('duplicates.fields.name'))->state(fn (DuplicateReviewCase $record): string => $other($record)['name']),
                    TextEntry::make('other_dob')->label(__('duplicates.fields.date_of_birth'))->state(fn (DuplicateReviewCase $record): string => $other($record)['date_of_birth'] ?? '—'),
                    TextEntry::make('other_nationality')->label(__('duplicates.fields.nationality'))->state(fn (DuplicateReviewCase $record): string => $other($record)['nationality'] ?? '—'),
                ]),
                KeyValueEntry::make('details')->label(__('duplicates.fields.details'))->columnSpanFull()
                    ->state(fn (DuplicateReviewCase $record): array => collect($record->match_details)
                        ->except(['candidate_name', 'candidate_date_of_birth'])
                        ->map(fn (mixed $value): string => match (true) {
                            $value === true => '✓',
                            $value === false => '✗',
                            $value === null => '—',
                            default => (string) $value,
                        })->all()),
                TextEntry::make('notes')->label(__('duplicates.fields.notes'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('candidate_name')->label(__('duplicates.fields.candidate_name'))
                    ->state(fn (DuplicateReviewCase $record): string => (string) ($record->match_details['candidate_name'] ?? '—')),
                TextColumn::make('match_reason')->label(__('duplicates.fields.match_reason'))
                    ->formatStateUsing(fn (MatchReason $state): string => $state->label()),
                TextColumn::make('match_score')->label(__('duplicates.fields.match_score')),
                TextColumn::make('status')->label(__('duplicates.fields.status'))->badge()
                    ->formatStateUsing(fn (ReviewStatus $state): string => $state->label())
                    ->color(fn (ReviewStatus $state): string => $state === ReviewStatus::Resolved ? 'success' : 'warning'),
                TextColumn::make('decision')->label(__('duplicates.fields.decision'))->placeholder('—')
                    ->formatStateUsing(fn (ReviewDecision $state): string => $state->label()),
                TextColumn::make('creator.name')->label(__('duplicates.fields.opened_by')),
                TextColumn::make('created_at')->label(__('duplicates.fields.opened_at'))
                    ->since(timezone: (string) config('app.display_timezone')),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('duplicates.fields.status'))
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')->label(__('duplicates.actions.resolve'))->icon('heroicon-o-scale')
                    ->authorize(fn (DuplicateReviewCase $record): bool => auth()->user()?->can('resolve', $record) ?? false)
                    ->schema([
                        Radio::make('decision')->label(__('duplicates.fields.decision'))->required()
                            ->options(collect(ReviewDecision::cases())->mapWithKeys(fn (ReviewDecision $decision): array => [$decision->value => $decision->label()])->all())
                            ->descriptions([
                                ReviewDecision::SamePerson->value => __('duplicates.help.same_person'),
                                ReviewDecision::DifferentPerson->value => __('duplicates.help.different_person'),
                            ]),
                        Textarea::make('notes')->label(__('duplicates.fields.notes'))->required()->maxLength(2000),
                    ])
                    ->action(function (DuplicateReviewCase $record, array $data, Action $action): void {
                        $user = auth()->user();

                        try {
                            if ($user instanceof User) {
                                app(ResolveDuplicateCase::class)->handle($record, ReviewDecision::from((string) $data['decision']), $user, $data['notes'] ?? null);
                            }
                        } catch (RuleViolation $violation) {
                            Notification::make()->danger()->title($violation->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title(__('duplicates.notifications.resolved'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDuplicateReviewCases::route('/'),
        ];
    }
}
