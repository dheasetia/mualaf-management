<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Exams\Pages;

use App\Domain\Examinations\Actions\ExamFinalization;
use App\Domain\Examinations\Actions\ManageExams;
use App\Domain\Examinations\Enums\ExamStatus;
use App\Domain\Examinations\Models\Exam;
use App\Filament\Center\Resources\Exams\ExamResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Exam $record
 */
final class ViewExam extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
        $status = fn (): ExamStatus => $this->record->refresh()->status;

        return [
            Action::make('start')->label(__('exams.actions.start'))->color('success')
                ->visible(fn (): bool => $can('exams.manage') && $status() === ExamStatus::Scheduled)
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageExams::class)->start($this->record, $user), 'exams.notifications.saved')),
            Action::make('submit')->label(__('exams.actions.submit'))
                ->visible(fn (): bool => $can('exams.grade') && $status() === ExamStatus::Graded)
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ExamFinalization::class)->submit($this->record, $user), 'exams.notifications.saved')),
            Action::make('reopen')->label(__('exams.actions.reopen'))->color('gray')
                ->visible(fn (): bool => $can('exams.finalize') && $status() === ExamStatus::PendingFinalization)
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ExamFinalization::class)->reopen($this->record, $user), 'exams.notifications.saved')),
            Action::make('finalize')->label(__('exams.actions.finalize'))->color('success')->requiresConfirmation()
                ->visible(fn (): bool => $can('exams.finalize') && $status() === ExamStatus::PendingFinalization)
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ExamFinalization::class)->finalize($this->record, $user), 'exams.notifications.saved')),
            Action::make('cancel')->label(__('exams.actions.cancel'))->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => $can('exams.manage') && in_array(ExamStatus::Cancelled, $status()->allowedTransitions(), true))
                ->action(self::domainAction(fn (mixed $record, array $data, User $user) => app(ManageExams::class)->cancel($this->record, $user), 'exams.notifications.saved')),
            EditAction::make()->visible(fn (): bool => $can('exams.manage') && $status() === ExamStatus::Scheduled),
        ];
    }
}
