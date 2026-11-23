<?php

declare(strict_types=1);

use App\Domain\Administration\Enums\SubmissionStatus;
use App\Domain\Administration\Models\Submission;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function (): void {
    seedRbac();
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH']);
    $this->clerk = grantRole(memberOf($this->dammam), 'administrative_officer', $this->dammam);
    $this->approver = grantRole(memberOf($this->dammam), 'approver', $this->dammam);
    $this->author = grantRole(memberOf($this->dammam), 'administrative_officer', $this->dammam);
    $this->elsewhere = grantRole(memberOf($this->riyadh), 'administrative_officer', $this->riyadh);
    $this->submission = fn (Tenant $tenant, SubmissionStatus $status, string $due, array $extra = []): Submission => inTenant($tenant,
        fn () => Submission::factory()->create(['status' => $status, 'due_at' => now()->parse($due), 'created_by' => $this->author->id, ...$extra]));
    $this->kinds = fn (User $user): array => DatabaseNotification::query()->where('notifiable_id', $user->id)->orderBy('created_at')
        ->get()->map(fn (DatabaseNotification $notification): string => $notification->data['kind'].':'.$notification->data['body'])->all();
});

it('reminds the people responsible for overdue submissions each day and escalates long-overdue ones once', function (): void {
    ($this->submission)($this->dammam, SubmissionStatus::UnderVerification, '-1 day');
    ($this->submission)($this->dammam, SubmissionStatus::Processing, '-5 days');
    ($this->submission)($this->dammam, SubmissionStatus::NeedsCorrection, '-2 days');
    ($this->submission)($this->dammam, SubmissionStatus::Submitted, '+2 days');
    ($this->submission)($this->dammam, SubmissionStatus::Completed, '-9 days');
    ($this->submission)($this->riyadh, SubmissionStatus::Submitted, '-9 days');

    $this->artisan('submissions:notify-overdue')->assertSuccessful();
    $this->artisan('submissions:notify-overdue')->assertSuccessful();

    $reminder = __('notifications.submissions_overdue.body', ['count' => 2]);
    expect(($this->kinds)($this->clerk))->toBe(["submissions_overdue:{$reminder}", "submissions_overdue:{$reminder}"])
        ->and(($this->kinds)($this->approver))->toBe(['submissions_escalated:'.__('notifications.submissions_escalated.body', ['count' => 1])])
        ->and(Submission::query()->withoutGlobalScopes()->whereNotNull('escalated_at')->count())->toBe(1)
        ->and(($this->kinds)($this->elsewhere))->toHaveCount(2);

    // The creator is reminded of the correction, besides the verifier and processor roles they hold.
    expect(($this->kinds)($this->author))->toContain('submissions_overdue:'.__('notifications.submissions_overdue.body', ['count' => 3]));
});

it('reminds only the assignee of an assigned submission', function (): void {
    $assignee = grantRole(memberOf($this->dammam), 'administrative_officer', $this->dammam);
    ($this->submission)($this->dammam, SubmissionStatus::Submitted, '-1 day', ['assigned_to' => $assignee->id]);

    $this->artisan('submissions:notify-overdue')->assertSuccessful();

    expect(($this->kinds)($assignee))->toHaveCount(1)
        ->and(($this->kinds)($this->clerk))->toBe([]);
});
