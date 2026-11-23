<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\Submissions\Pages;

use App\Domain\Administration\Actions\SubmissionWorkflow;
use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Filament\Center\Resources\Submissions\SubmissionResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

/**
 * Submission page: each step is shown to who may take it at the current
 * status; SubmissionWorkflow decides.
 *
 * @property Submission $record
 */
final class ViewSubmission extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = SubmissionResource::class;

    protected function getHeaderActions(): array
    {
        $workflow = fn (): SubmissionWorkflow => app(SubmissionWorkflow::class);
        $at = fn (SubmissionStatus ...$statuses): bool => in_array($this->record->status, $statuses, true);
        $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
        $reason = fn (string $field = 'reason'): Textarea => Textarea::make($field)->label(__('submissions.fields.'.$field))->required()->maxLength(1000);
        $step = fn (string $name, string $label, Closure $visible, Closure $handler): Action => Action::make($name)->label(__('submissions.actions.'.$label))
            ->visible($visible)
            ->action(self::domainAction(function (mixed $record, array $data, User $actor) use ($handler): void {
                $handler($this->record, $data, $actor);
                $this->record->refresh();
            }, 'submissions.notifications.saved'));

        return [
            $step('submit', 'submit', fn (): bool => $at(SubmissionStatus::Draft, SubmissionStatus::NeedsCorrection) && $can('submissions.create'),
                fn ($submission, $data, $actor) => $workflow()->submit($submission, $actor))->color('primary')->requiresConfirmation(),
            $step('startVerification', 'start_verification', fn (): bool => $at(SubmissionStatus::Submitted) && $can('submissions.verify'),
                fn ($submission, $data, $actor) => $workflow()->startVerification($submission, $actor)),
            $step('approve', 'approve', fn (): bool => $at(SubmissionStatus::UnderVerification) && $can('submissions.approve') && $this->record->created_by !== auth()->id(),
                fn ($submission, $data, $actor) => $workflow()->approve($submission, $actor))->color('success')->requiresConfirmation(),
            $step('requestCorrection', 'request_correction', fn (): bool => $at(SubmissionStatus::UnderVerification) && ($can('submissions.verify') || $can('submissions.approve')),
                fn ($submission, $data, $actor) => $workflow()->requestCorrection($submission, $actor, $data['note'] ?? null))->schema([$reason('note')])->color('warning'),
            $step('reject', 'reject', fn (): bool => $at(SubmissionStatus::UnderVerification) && ($can('submissions.verify') || $can('submissions.approve')),
                fn ($submission, $data, $actor) => $workflow()->reject($submission, $actor, $data['reason'] ?? null))->schema([$reason()])->color('danger'),
            $step('startProcessing', 'start_processing', fn (): bool => $at(SubmissionStatus::Approved) && $can('submissions.process'),
                fn ($submission, $data, $actor) => $workflow()->startProcessing($submission, $actor)),
            $step('complete', 'complete', fn (): bool => $at(SubmissionStatus::Processing) && $can('submissions.process'),
                fn ($submission, $data, $actor) => $workflow()->complete($submission, $data['addressee'] ?? null, $actor))
                ->schema([TextInput::make('addressee')->label(__('submissions.fields.addressee'))->required()->maxLength(255)])->color('success'),
            ActionGroup::make([
                $step('cancel', 'cancel', fn (): bool => $this->record->status->canTransitionTo(SubmissionStatus::Cancelled)
                    && ($can('submissions.approve') || ($this->record->created_by === auth()->id() && $at(SubmissionStatus::Draft, SubmissionStatus::Submitted, SubmissionStatus::NeedsCorrection))),
                    fn ($submission, $data, $actor) => $workflow()->cancel($submission, $actor, $data['reason'] ?? null))->schema([$reason()])->color('danger'),
            ]),
        ];
    }
}
