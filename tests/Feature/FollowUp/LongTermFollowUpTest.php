<?php

declare(strict_types=1);

use App\Domain\Converts\Enums\ConvertStatus;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Enums\Confidentiality;
use App\Domain\FollowUp\Actions\LongTermFollowUps;
use App\Domain\FollowUp\Actions\RecordFollowUpStatus;
use App\Domain\FollowUp\Enums\FollowUpState;
use App\Domain\FollowUp\Enums\Priority;
use App\Domain\FollowUp\Enums\ScheduleStatus;
use App\Domain\FollowUp\Enums\TriggerEvent;
use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\FollowUp\Models\ConvertFollowUpStatus;
use App\Domain\FollowUp\Models\ConvertLongTermFollowUp;
use App\Domain\FollowUp\Models\FollowUpScheduleTemplate;
use App\Domain\Guidance\Events\EnrollmentCompleted;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use Database\Seeders\FollowUpScheduleTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->seed(FollowUpScheduleTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create();
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->status = fn (Convert $convert, string $status, array $extra = []) => ($this->in)(fn () => app(RecordFollowUpStatus::class)
        ->handle($convert, ['status' => $status, ...$extra], $this->head));
    $this->contact = ['contact_method' => 'phone', 'contacted_person_type' => 'convert', 'contact_result' => 'reached'];
});

it('schedules the national follow-up rhythm for the primary da\'i when the shahadah is verified', function (): void {
    $convert = documentsConvert($this->tenant);

    $schedules = ($this->in)(fn () => ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->orderBy('scheduled_at')->get());

    expect($schedules)->toHaveCount(5)
        ->and($schedules->pluck('dai_id')->unique()->all())->toBe([$convert->primary_dai_id])
        ->and($schedules->first()->scheduled_at->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($schedules->pluck('follow_up_type')->unique()->all())->toBe(['shahadah']);
});

it('prefers the office\'s own template for a trigger', function (): void {
    ($this->in)(function (): void {
        $template = new FollowUpScheduleTemplate;
        $template->forceFill(['tenant_id' => $this->tenant->id, 'code' => 'after_guidance_local', 'name_ar' => 'محلي', 'trigger_event' => TriggerEvent::GuidanceCompleted, 'offsets' => [14], 'active' => true])->save();
    });
    $convert = documentsConvert($this->tenant);

    EnrollmentCompleted::dispatch(1, $convert->id, $this->tenant->id);

    expect(($this->in)(fn () => ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->where('follow_up_type', 'guidance_completed')->pluck('scheduled_at')
        ->map(fn ($at) => $at->toDateString())->all()))->toBe([now()->addDays(14)->toDateString()]);
});

it('records follow-up statuses as history, keeps the convert\'s record status, and starts the return-home rhythm once', function (): void {
    $convert = documentsConvert($this->tenant);
    ($this->status)($convert, 'needs_follow_up');
    $latest = ($this->status)($convert, 'returned_home', ['last_known_country_id' => Country::factory()->create()->id]);
    ($this->status)($convert, 'returned_home');

    ($this->in)(function () use ($convert, $latest): void {
        $fresh = Convert::query()->findOrFail($convert->id);

        expect(ConvertFollowUpStatus::query()->where('convert_id', $convert->id)->count())->toBe(3)
            ->and($fresh->status)->toBe(ConvertStatus::Active)
            ->and($fresh->current_follow_up_status_id)->toBeGreaterThan($latest->id)
            ->and(ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->where('follow_up_type', 'returned_home')->count())->toBe(5);

        expect(fn () => $latest->forceFill(['status' => FollowUpState::Stable])->save())->toThrow(LogicException::class);
    });
});

it('completes a scheduled follow-up with the contact and schedules the next one', function (): void {
    $convert = documentsConvert($this->tenant);

    ($this->in)(function () use ($convert): void {
        $schedule = ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->orderBy('scheduled_at')->firstOrFail();
        $note = app(LongTermFollowUps::class)->record($convert, [...$this->contact, 'next_follow_up_at' => now()->addMonth()->toDateTimeString()], $schedule, $this->head);

        expect($schedule->refresh())->status->toBe(ScheduleStatus::Completed)->long_term_follow_up_id->toBe($note->id)->outcome->toBe('reached')
            ->and(ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->where('follow_up_type', 'long_term')->count())->toBe(1);

        expectRuleViolation(fn () => app(LongTermFollowUps::class)->record($convert, $this->contact, $schedule->refresh(), $this->head), 'status');
    });
});

it('reschedules, misses or cancels only scheduled follow-ups, and never into the past', function (): void {
    $convert = documentsConvert($this->tenant);

    ($this->in)(function () use ($convert): void {
        [$first, $second] = ConvertFollowUpSchedule::query()->where('convert_id', $convert->id)->orderBy('scheduled_at')->take(2)->get()->all();
        $followUps = app(LongTermFollowUps::class);

        expectRuleViolation(fn () => $followUps->reschedule($first, now()->subDay()->toImmutable(), $this->head), 'scheduled_at');
        $moved = $followUps->reschedule($first, now()->addDays(3)->toImmutable(), $this->head);
        expect($first->refresh()->status)->toBe(ScheduleStatus::Rescheduled)->and($moved->status)->toBe(ScheduleStatus::Scheduled);

        expectRuleViolation(fn () => $followUps->cancel($second, '', $this->head), 'reason');
        $followUps->miss($second, $this->head);
        expectRuleViolation(fn () => $followUps->miss($second->refresh(), $this->head), 'status');

        expect(fn () => $followUps->miss($moved, grantRole(memberOf($this->tenant), 'teacher', $this->tenant)))->toThrow(AuthorizationException::class);
    });
});

it('shows confidential notes only to followups.view_confidential and their author', function (): void {
    $convert = documentsConvert($this->tenant);
    $dai = grantRole(memberOf($this->tenant), 'dai', $this->tenant);

    ($this->in)(function () use ($convert, $dai): void {
        app(LongTermFollowUps::class)->record($convert, [...$this->contact, 'confidentiality_level' => Confidentiality::Confidential->value], null, $this->head);
        app(LongTermFollowUps::class)->record($convert, $this->contact, null, $this->head);

        expect(ConvertLongTermFollowUp::query()->visibleTo($this->head)->count())->toBe(2)
            ->and(ConvertLongTermFollowUp::query()->visibleTo($dai)->count())->toBe(1);
    });
});

it('only suggests follow-ups for converts nobody contacted for a while, never writing a status (PRD §31)', function (): void {
    [$quiet, $contacted, $planned] = collect(['277004411', '277004422', '277004433'])->map(fn (string $identity) => documentsConvert($this->tenant, identity: $identity))->all();
    ($this->in)(function () use ($quiet, $contacted): void {
        ConvertFollowUpSchedule::query()->whereIn('convert_id', [$quiet->id, $contacted->id])->update(['status' => ScheduleStatus::Cancelled]);
        Convert::query()->update(['created_at' => now()->subDays(120)]);
        app(LongTermFollowUps::class)->record($contacted, $this->contact, null, $this->head);
    });

    $this->artisan('followups:flag-quiet')->assertSuccessful();

    ($this->in)(function () use ($quiet, $contacted, $planned): void {
        $suggested = ConvertFollowUpSchedule::query()->where('follow_up_type', 'check_in')->get();

        expect($suggested->pluck('convert_id')->all())->toBe([$quiet->id])
            ->and($suggested->first()->priority)->toBe(Priority::High)
            ->and(ConvertFollowUpStatus::query()->count())->toBe(0)
            ->and(Convert::query()->whereKey([$quiet->id, $contacted->id, $planned->id])->pluck('status')->unique()->all())->toBe([ConvertStatus::Active]);
    });
});
