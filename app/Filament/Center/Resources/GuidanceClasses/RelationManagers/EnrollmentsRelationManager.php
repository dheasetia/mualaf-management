<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\GuidanceClasses\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Guidance\Actions\ClassRecords;
use App\Domain\Guidance\Actions\ManageEnrollments;
use App\Domain\Guidance\Enums\EnrollmentStatus;
use App\Domain\Guidance\Enums\ProgressStatus;
use App\Domain\Guidance\Models\Enrollment;
use App\Domain\Guidance\Models\GuidanceClass;
use App\Domain\Guidance\Models\GuidanceLesson;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class EnrollmentsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'enrollments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('guidance.enrollments');
    }

    /** Actions here check their own permissions; the view page must not hide them. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('convert'))
            ->columns([
                TextColumn::make('convert_name')->label(__('guidance.fields.convert'))
                    ->state(function (Enrollment $record): string {
                        $user = self::user();

                        return $user === null ? '—' : app(ConvertAccess::class)->displayName($user, $record->convert);
                    }),
                TextColumn::make('status')->label(__('guidance.fields.status'))->badge()
                    ->formatStateUsing(fn (EnrollmentStatus $state): string => $state->label()),
                TextColumn::make('attendance_percentage')->label(__('guidance.fields.attendance_percentage'))->placeholder('—'),
                TextColumn::make('enrolled_at')->label(__('guidance.fields.enrolled_at'))->date('Y-m-d'),
                TextColumn::make('target_completion_date')->label(__('guidance.fields.target_completion_date'))->date('Y-m-d'),
            ])
            ->headerActions([
                Action::make('enroll')->label(__('guidance.actions.enroll'))
                    ->visible(fn (): bool => self::user()?->hasPermission('guidance.enroll') ?? false)
                    ->schema([
                        Select::make('convert_id')->label(__('guidance.fields.convert'))->required()->searchable()
                            ->options(function (): array {
                                $user = self::user();

                                if ($user === null) {
                                    return [];
                                }

                                return app(ConvertAccess::class)
                                    ->visible(Convert::query()->where('status', 'active'), $user)
                                    ->orderBy('full_name')->limit(500)->get()
                                    ->mapWithKeys(fn (Convert $convert): array => [$convert->id => app(ConvertAccess::class)->displayName($user, $convert)])
                                    ->all();
                            }),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageEnrollments::class)
                        ->enroll((int) $data['convert_id'], $this->ownerClass()->program_id, $this->ownerClass()->id, $user))),
            ])
            ->recordActions([
                Action::make('recordProgress')->label(__('guidance.actions.record_progress'))->icon('heroicon-o-check-circle')
                    ->visible(fn (): bool => self::user() !== null && (self::user()->can('teach', $this->ownerClass())) && self::user()->hasPermission('guidance.progress.record'))
                    ->schema([
                        Section::make()->columnSpanFull()->schema([
                            Fieldset::make(__('forms.details'))->columns(2)->schema([
                                Select::make('lesson_id')->label(__('guidance.fields.lesson'))->required()->searchable()
                                    ->options(fn (): array => GuidanceLesson::query()
                                        ->whereHas('module', fn (Builder $query) => $query->where('program_id', $this->ownerClass()->program_id))
                                        ->orderBy('module_id')->orderBy('sort_order')->pluck('title', 'id')->all()),
                                Select::make('progress_status')->label(__('guidance.fields.progress_status'))->required()->options(collect(ProgressStatus::cases())->mapWithKeys(fn (ProgressStatus $case): array => [$case->value => $case->label()])->all()),
                                TextInput::make('score')->label(__('guidance.fields.score'))->numeric()->minValue(0)->maxValue(100),
                                Toggle::make('needs_mentoring')->label(__('guidance.fields.needs_mentoring')),
                            ]),
                            Fieldset::make(__('forms.notes'))->columns(1)->schema([
                                Textarea::make('competency_notes')->label(__('guidance.fields.competency_notes'))->maxLength(2000),
                            ]),
                        ]),
                    ])
                    ->action(self::domainAction(fn (Enrollment $record, array $data, User $user) => app(ClassRecords::class)
                        ->recordProgress($record, (int) $data['lesson_id'], [
                            'progress_status' => (string) $data['progress_status'],
                            'score' => blank($data['score'] ?? null) ? null : (string) $data['score'],
                            'competency_notes' => blank($data['competency_notes'] ?? null) ? null : (string) $data['competency_notes'],
                            'needs_mentoring' => (bool) ($data['needs_mentoring'] ?? false),
                        ], $user))),
                Action::make('changeStatus')->label(__('guidance.actions.change_status'))
                    ->visible(fn (Enrollment $record): bool => (self::user()?->hasPermission('guidance.enroll') ?? false) && $record->status->allowedTransitions() !== [])
                    ->schema([
                        Select::make('status')->label(__('guidance.fields.status'))->required()
                            ->options(fn (Enrollment $record): array => collect($record->status->allowedTransitions())
                                ->reject(fn (EnrollmentStatus $status): bool => $status === EnrollmentStatus::Transferred)
                                ->mapWithKeys(fn (EnrollmentStatus $status): array => [$status->value => $status->label()])->all()),
                        Textarea::make('reason')->label(__('guidance.fields.reason'))->maxLength(500),
                    ])
                    ->action(self::domainAction(fn (Enrollment $record, array $data, User $user) => app(ManageEnrollments::class)
                        ->changeStatus($record, EnrollmentStatus::from((string) $data['status']), $user, $data['reason'] ?? null))),
            ]);
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function ownerClass(): GuidanceClass
    {
        $class = $this->getOwnerRecord();

        return $class instanceof GuidanceClass ? $class : abort(404);
    }
}
