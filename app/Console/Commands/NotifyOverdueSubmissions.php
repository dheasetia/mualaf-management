<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Notifications\Contracts\OfficeLinks;
use App\Domain\Notifications\Notifications\OfficeAlert;
use App\Domain\Notifications\Services\OfficeRecipients;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Submission SLA (10 §10.1): each morning whoever holds an open submission
 * past its due date gets one summary: the assignee when there is one,
 * otherwise the creator for a correction, the verifiers before approval and
 * the processors after it. Submissions overdue for more than
 * SUBMISSION_ESCALATION_DAYS are escalated once to the office's approvers.
 */
final class NotifyOverdueSubmissions extends Command
{
    private const array OPEN = [
        SubmissionStatus::Submitted, SubmissionStatus::UnderVerification, SubmissionStatus::NeedsCorrection,
        SubmissionStatus::Approved, SubmissionStatus::Processing,
    ];

    protected $signature = 'submissions:notify-overdue';

    protected $description = 'Remind staff of submissions past their SLA and escalate long-overdue ones';

    public function handle(TenantContext $tenantContext, OfficeRecipients $recipients, OfficeLinks $links): int
    {
        $sent = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $sent += (int) $tenantContext->run($tenant, function () use ($tenant, $recipients, $links): int {
                $now = CarbonImmutable::now();
                $url = $links->submissions($tenant);
                $overdue = Submission::query()->whereIn('status', self::OPEN)->where('due_at', '<', $now)
                    ->get(['id', 'status', 'assigned_to', 'created_by', 'due_at', 'escalated_at']);

                /** @var array<int, array{user: User, count: int}> $reminders */
                $reminders = [];

                foreach ($overdue as $submission) {
                    foreach ($this->responsible($submission, $tenant->id, $recipients) as $user) {
                        $reminders[$user->id] ??= ['user' => $user, 'count' => 0];
                        $reminders[$user->id]['count']++;
                    }
                }

                foreach ($reminders as ['user' => $user, 'count' => $count]) {
                    $user->notify(new OfficeAlert('submissions_overdue', $tenant->id, ['count' => $count], $url));
                }

                $sent = count($reminders);
                $limit = $now->subDays((int) config('submissions.escalate_after_days'));
                $longOverdue = $overdue->filter(fn (Submission $submission): bool => $submission->escalated_at === null
                    && $submission->due_at !== null && $submission->due_at->lessThan($limit));

                $approvers = $longOverdue->isEmpty() ? collect() : $recipients->withPermission($tenant->id, 'submissions.approve');

                // Marked only once someone was told, so an office without approvers is escalated when it gets one.
                if ($approvers->isNotEmpty()) {
                    Notification::send($approvers, new OfficeAlert('submissions_escalated', $tenant->id, ['count' => $longOverdue->count()], $url));
                    Submission::query()->whereKey($longOverdue->modelKeys())->update(['escalated_at' => $now]);
                    $sent += $approvers->count();
                }

                return $sent;
            });
        }

        $this->info("{$sent} notification(s) sent.");

        return self::SUCCESS;
    }

    /** @return Collection<int, User> */
    private function responsible(Submission $submission, int $tenantId, OfficeRecipients $recipients): Collection
    {
        $userId = $submission->assigned_to ?? ($submission->status === SubmissionStatus::NeedsCorrection ? $submission->created_by : null);

        if ($userId !== null) {
            return User::query()->whereKey($userId)->where('is_active', true)->get();
        }

        $permission = in_array($submission->status, [SubmissionStatus::Approved, SubmissionStatus::Processing], true)
            ? 'submissions.process' : 'submissions.verify';

        return $recipients->withPermission($tenantId, $permission);
    }
}
