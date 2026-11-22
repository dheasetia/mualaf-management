<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exams\RelationManagers;

use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\ConvertAccess;
use App\Domain\Examinations\Actions\ExamFinalization;
use App\Domain\Examinations\Actions\GradeAttempt;
use App\Domain\Examinations\Actions\ManageExams;
use App\Domain\Examinations\Enums\AttemptStatus;
use App\Domain\Examinations\Enums\ResultStatus;
use App\Domain\Examinations\Models\Exam;
use App\Domain\Examinations\Models\ExamAttempt;
use App\Domain\Examinations\Models\ExamComponent;
use App\Domain\Guidance\Services\EnrollmentDirectory;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AttemptsRelationManager extends RelationManager
{
    use RunsDomainActions;

    protected static string $relationship = 'attempts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('exams.attempts');
    }

    /** Actions check their own permissions. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('result'))
            ->columns([
                TextColumn::make('convert')->label(__('exams.fields.convert'))
                    ->state(fn (ExamAttempt $record): string => $this->convertName($record->enrollment_id)),
                TextColumn::make('attempt_number')->label(__('exams.fields.attempt_number')),
                TextColumn::make('status')->label(__('exams.fields.status'))->badge()->formatStateUsing(fn (AttemptStatus $state): string => $state->label()),
                TextColumn::make('result.final_score')->label(__('exams.fields.final_score'))->placeholder('—'),
                IconColumn::make('result.passed')->label(__('exams.fields.passed'))->boolean(),
                TextColumn::make('result.status')->label(__('exams.fields.result_status'))->placeholder('—')
                    ->formatStateUsing(fn (ResultStatus $state): string => $state->label()),
            ])
            ->headerActions([
                Action::make('addAttempts')->label(__('exams.actions.add_attempts'))->visible(fn (): bool => $can('exams.manage'))
                    ->schema([
                        Select::make('enrollment_ids')->label(__('exams.fields.enrollments'))->multiple()->required()
                            ->options(fn (): array => app(EnrollmentDirectory::class)->eligible($this->exam()->program_id, $this->exam()->class_id)
                                ->mapWithKeys(fn (int $convertId, int $enrollmentId): array => [$enrollmentId => $this->convertName($enrollmentId)])->all()),
                    ])
                    ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageExams::class)
                        ->addAttempts($this->exam(), array_values(array_map(intval(...), (array) $data['enrollment_ids'])), $user), 'exams.notifications.saved')),
            ])
            ->recordActions([
                Action::make('grade')->label(__('exams.actions.grade'))->icon('heroicon-o-pencil')
                    ->visible(fn (ExamAttempt $record): bool => $can('exams.grade') && $this->exam()->status->acceptsGrading() && $record->status !== AttemptStatus::Finalized)
                    ->schema(fn (): array => ExamComponent::query()->where('exam_id', $this->exam()->id)->get()
                        ->map(fn (ExamComponent $component) => TextInput::make('score_'.$component->id)
                            ->label($component->name.' / '.$component->max_score)->numeric()->minValue(0)->maxValue((float) $component->max_score)->required())
                        ->all())
                    ->action(self::domainAction(fn (ExamAttempt $record, array $data, User $user) => app(GradeAttempt::class)->grade(
                        $record,
                        ExamComponent::query()->where('exam_id', $record->exam_id)->pluck('id')
                            ->mapWithKeys(fn (int $id): array => [$id => (string) ($data['score_'.$id] ?? '')])->all(),
                        $user,
                    ), 'exams.notifications.saved')),
                Action::make('absent')->label(__('exams.actions.absent'))->color('gray')->requiresConfirmation()
                    ->visible(fn (ExamAttempt $record): bool => $can('exams.grade') && $this->exam()->status->acceptsGrading() && $record->status === AttemptStatus::Scheduled)
                    ->action(self::domainAction(fn (ExamAttempt $record, array $data, User $user) => app(GradeAttempt::class)->markAbsent($record, $user), 'exams.notifications.saved')),
                Action::make('amend')->label(__('exams.actions.amend'))->color('warning')
                    ->visible(fn (ExamAttempt $record): bool => $can('exams.finalize') && $record->result !== null && $record->result->status !== ResultStatus::Provisional)
                    ->schema([
                        TextInput::make('final_score')->label(__('exams.fields.final_score'))->numeric()->minValue(0)->required(),
                        Textarea::make('reason')->label(__('exams.fields.reason'))->required()->maxLength(1000),
                    ])
                    ->action(self::domainAction(fn (ExamAttempt $record, array $data, User $user) => $record->result === null ? null : app(ExamFinalization::class)
                        ->amend($record->result, (float) $data['final_score'], $user, $data['reason'] ?? null), 'exams.notifications.saved')),
            ]);
    }

    private function exam(): Exam
    {
        $exam = $this->getOwnerRecord();

        return $exam instanceof Exam ? $exam->refresh() : abort(404);
    }

    private function convertName(int $enrollmentId): string
    {
        $user = auth()->user();
        $convertId = app(EnrollmentDirectory::class)->convertOf($enrollmentId);
        $convert = $convertId === null ? null : Convert::query()->find($convertId);

        return $user instanceof User && $convert instanceof Convert ? app(ConvertAccess::class)->displayName($user, $convert) : '—';
    }
}
