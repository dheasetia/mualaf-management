<?php

declare(strict_types=1);

use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\RecordDailyActivity;
use App\Domain\Dawah\Enums\ActivityStatus;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    $this->daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->dai = inTenant($this->tenant, fn () => Dai::factory()->create(['user_id' => $this->daiUser->id]));
    $this->reviewed = DaiActivityType::factory()->create(['requires_verification' => true]);
    $this->simple = DaiActivityType::factory()->create(['requires_verification' => false]);
    $this->record = fn (array $overrides = [], ?DaiDailyActivity $activity = null) => inTenant($this->tenant, fn () => app(RecordDailyActivity::class)->save($activity, [
        'activity_date' => now()->toDateString(),
        'activity_type_id' => $this->reviewed->id,
        'title' => 'Mosque visit',
        'started_at' => '09:00',
        'ended_at' => '10:30',
        ...$overrides,
    ], $this->daiUser));
});

it('records an activity for the da\'i with its duration from the times', function (): void {
    expect(($this->record)())->dai_id->toBe($this->dai->id)->duration_minutes->toBe(90)->status->toBe(ActivityStatus::Draft);
});

it('refuses future dates and links to converts the da\'i is not assigned to', function (): void {
    expectRuleViolation(fn () => ($this->record)(['activity_date' => now()->addDays(2)->toDateString()]), 'activity_date');

    $notMine = inTenant($this->tenant, fn () => Convert::factory()->create());
    expectRuleViolation(fn () => ($this->record)(['related_convert_id' => $notMine->id]), 'related_convert_id');
});

it('verifies at once when the type needs no review, else waits for Head of Dawah', function (): void {
    inTenant($this->tenant, function (): void {
        $simple = app(RecordDailyActivity::class)->submit(($this->record)(['activity_type_id' => $this->simple->id]), $this->daiUser);
        $reviewed = app(RecordDailyActivity::class)->submit(($this->record)(), $this->daiUser);

        expect($simple->status)->toBe(ActivityStatus::Verified)
            ->and($reviewed->status)->toBe(ActivityStatus::Submitted);

        expectRuleViolation(fn () => ($this->record)(['title' => 'Changed'], $reviewed), 'status');
        expect(fn () => app(RecordDailyActivity::class)->verify($reviewed, $this->daiUser))->toThrow(AuthorizationException::class);
        expectRuleViolation(fn () => app(RecordDailyActivity::class)->reject($reviewed, $this->head, ''), 'reason');

        app(RecordDailyActivity::class)->reject($reviewed, $this->head, 'Add the place');
        expect(($this->record)(['title' => 'Mosque visit, Dammam'], $reviewed->refresh())->title)->toBe('Mosque visit, Dammam');
    });
});

it('does not let Head of Dawah verify their own activity', function (): void {
    $both = grantRole($this->daiUser, 'head_of_dawah', $this->tenant);

    inTenant($this->tenant, function () use ($both): void {
        $activity = app(RecordDailyActivity::class)->submit(($this->record)(), $this->daiUser);
        expectRuleViolation(fn () => app(RecordDailyActivity::class)->verify($activity, $both), 'status');
    });
});
