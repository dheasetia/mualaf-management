<?php

declare(strict_types=1);

namespace App\Filament\Center\Resources\DawahPlans\Pages;

use App\Domain\Dawah\Actions\PlanWorkflow;
use App\Domain\Dawah\Actions\ReportWorkflow;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Services\DawahActors;
use App\Filament\Center\Resources\DawahPlans\DawahActivityPlanResource;
use App\Filament\Concerns\RunsDomainActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;

/**
 * Plan page: each workflow step is an action shown only to who may take it
 * at the current status; PlanWorkflow and ReportWorkflow decide.
 *
 * @property DawahActivityPlan $record
 */
final class ViewDawahActivityPlan extends ViewRecord
{
    use RunsDomainActions;

    protected static string $resource = DawahActivityPlanResource::class;

    protected function getHeaderActions(): array
    {
        $plans = fn (): PlanWorkflow => app(PlanWorkflow::class);
        $reports = fn (): ReportWorkflow => app(ReportWorkflow::class);
        $at = fn (PlanStatus ...$statuses): bool => in_array($this->record->status, $statuses, true);
        $user = fn (): ?User => ($user = auth()->user()) instanceof User ? $user : null;
        $can = fn (string $permission): bool => $user()?->hasPermission($permission) ?? false;
        $isPlanDai = fn (): bool => ($u = $user()) !== null && app(DawahActors::class)->isPlanDai($u, $this->record);
        $isResponsible = fn (): bool => ($u = $user()) !== null && app(DawahActors::class)->isResponsible($u, $this->record);
        $runs = fn (): bool => ($u = $user()) !== null && app(DawahActors::class)->runsPlan($u, $this->record);
        $reason = fn (string $field = 'reason'): Textarea => Textarea::make($field)->label(__('dawah.fields.'.$field))->required()->maxLength(1000);
        $step = fn (string $name, string $label, bool|\Closure $visible, \Closure $handler): Action => Action::make($name)->label(__('dawah.actions.'.$label))
            ->visible($visible)
            ->action(self::domainAction(function (mixed $record, array $data, User $actor) use ($handler): void {
                $handler($this->record, $data, $actor);
                $this->record->refresh();
            }, 'dawah.notifications.saved'));

        return [
            EditAction::make()->visible(fn (): bool => $user()?->can('update', $this->record) ?? false),
            $step('submit', 'submit', fn (): bool => $at(PlanStatus::Draft, PlanStatus::NeedsRevision) && $can('dawah.plans.submit') && $isPlanDai(),
                fn ($plan, $data, $actor) => $plans()->submit($plan, $actor))->color('primary')->requiresConfirmation(),
            $step('startReview', 'start_review', fn (): bool => $at(PlanStatus::Submitted) && $can('dawah.plans.review'),
                fn ($plan, $data, $actor) => $plans()->startReview($plan, $actor)),
            $step('approve', 'approve', fn (): bool => $at(PlanStatus::SupervisorReview) && $can('dawah.plans.approve') && ! $isPlanDai(),
                fn ($plan, $data, $actor) => $plans()->approve($plan, $actor))->color('success')->requiresConfirmation(),
            $step('requestRevision', 'request_revision', fn (): bool => $at(PlanStatus::SupervisorReview) && $can('dawah.plans.review'),
                fn ($plan, $data, $actor) => $plans()->requestRevision($plan, $actor, $data['note'] ?? null))->schema([$reason('note')])->color('warning'),
            $step('reject', 'reject', fn (): bool => $at(PlanStatus::SupervisorReview) && ($can('dawah.plans.review') || $can('dawah.plans.approve')),
                fn ($plan, $data, $actor) => $plans()->reject($plan, $actor, $data['reason'] ?? null))->schema([$reason()])->color('danger'),
            $step('schedule', 'schedule', fn (): bool => $at(PlanStatus::Approved) && $runs(),
                fn ($plan, $data, $actor) => $plans()->schedule($plan, $actor)),
            $step('start', 'start', fn (): bool => $at(PlanStatus::Scheduled) && $runs(),
                fn ($plan, $data, $actor) => $plans()->start($plan, $actor))->requiresConfirmation(),
            $step('complete', 'complete', fn (): bool => $at(PlanStatus::InProgress) && $runs(),
                fn ($plan, $data, $actor) => $plans()->complete($plan, $actor))->requiresConfirmation(),
            $step('writeReport', 'write_report', fn (): bool => $at(PlanStatus::Completed) && $can('dawah.reports.submit') && $isResponsible(),
                fn ($plan, $data, $actor) => $reports()->save($plan, $data, $actor))
                ->fillForm(fn (): array => $this->record->report?->toArray() ?? [])
                ->schema([
                    Section::make()->columnSpanFull()->schema([
                        Fieldset::make(__('forms.basic'))->columns(2)->schema([
                            Textarea::make('summary')->label(__('dawah.fields.summary'))->required()->maxLength(10000),
                            TextInput::make('actual_participants')->label(__('dawah.fields.actual_participants'))->integer()->minValue(0),
                            TextInput::make('new_prospects_count')->label(__('dawah.fields.new_prospects_count'))->integer()->minValue(0)->default(0),
                            TextInput::make('shahadah_count')->label(__('dawah.fields.shahadah_count'))->integer()->minValue(0)->default(0),
                        ]),
                        Fieldset::make(__('forms.schedule'))->columns(2)->schema([
                            DateTimePicker::make('actual_start_at')->label(__('dawah.fields.actual_start_at'))->seconds(false),
                            DateTimePicker::make('actual_end_at')->label(__('dawah.fields.actual_end_at'))->seconds(false)->afterOrEqual('actual_start_at'),
                        ]),
                        Fieldset::make(__('forms.result'))->columns(1)->schema([
                            Textarea::make('achievements')->label(__('dawah.fields.achievements'))->maxLength(5000),
                            Textarea::make('challenges')->label(__('dawah.fields.challenges'))->maxLength(5000),
                            Textarea::make('follow_up_actions')->label(__('dawah.fields.follow_up_actions'))->maxLength(5000),
                            Textarea::make('material_usage_summary')->label(__('dawah.fields.material_usage_summary'))->maxLength(2000),
                            Textarea::make('transport_summary')->label(__('dawah.fields.transport_summary'))->maxLength(2000),
                        ]),
                    ]),
                ]),
            $step('submitReport', 'submit_report', fn (): bool => $at(PlanStatus::Completed) && $this->record->report !== null && $can('dawah.reports.submit') && $isResponsible(),
                fn ($plan, $data, $actor) => $reports()->submit($plan, $actor))->color('primary')->requiresConfirmation(),
            $step('verifyReport', 'verify_report', fn (): bool => $at(PlanStatus::ReportSubmitted) && $can('dawah.reports.verify') && ! $isPlanDai(),
                fn ($plan, $data, $actor) => $reports()->verify($plan, $actor))->color('success')->requiresConfirmation(),
            $step('returnReport', 'return_report', fn (): bool => $at(PlanStatus::ReportSubmitted) && $can('dawah.reports.verify') && ! $isPlanDai(),
                fn ($plan, $data, $actor) => $reports()->return($plan, $actor, $data['note'] ?? null))->schema([$reason('note')])->color('warning'),
            ActionGroup::make([
                $step('cancel', 'cancel', fn (): bool => $this->record->status->canTransitionTo(PlanStatus::Cancelled)
                    && ($can('dawah.plans.approve') || ($at(PlanStatus::Draft, PlanStatus::Submitted, PlanStatus::NeedsRevision) && $can('dawah.plans.submit') && $isPlanDai())),
                    fn ($plan, $data, $actor) => $plans()->cancel($plan, $actor, $data['reason'] ?? null))->schema([$reason()])->color('danger'),
            ]),
        ];
    }
}
