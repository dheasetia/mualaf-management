<?php

declare(strict_types=1);

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\DaiActivities\Pages\ManageDaiDailyActivities;
use App\Filament\Center\Resources\DawahPlans\Pages\CreateDawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\Pages\ViewDawahActivityPlan;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->type = DaiActivityType::factory()->create();
    $this->daiUser = grantRole(memberOf($this->tenant), 'dai', $this->tenant);
    $this->head = withAuthenticator(grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant));
    inTenant($this->tenant, fn () => Dai::factory()->create(['user_id' => $this->daiUser->id]));
});

it('lets a da\'i propose and submit a plan, then Head of Dawah review and approve it', function (): void {
    inCenterPanel($this->tenant, $this->daiUser);
    Livewire::test(CreateDawahActivityPlan::class)
        ->fillForm(['title' => 'Open day', 'activity_type_id' => $this->type->id, 'objective' => 'Invite visitors', 'planned_date' => now()->addWeek()->toDateString()])
        ->call('create')
        ->assertHasNoFormErrors();
    $plan = inTenant($this->tenant, fn () => DawahActivityPlan::query()->sole());

    Livewire::test(ViewDawahActivityPlan::class, ['record' => $plan->getRouteKey()])
        ->assertActionHidden('approve')
        ->callAction('submit');

    inCenterPanel($this->tenant, $this->head);
    Livewire::test(ViewDawahActivityPlan::class, ['record' => $plan->getRouteKey()])
        ->assertActionHidden('submit')
        ->callAction('startReview')
        ->callAction('approve');

    expect(inTenant($this->tenant, fn () => $plan->refresh()->status))->toBe(PlanStatus::Approved);
});

it('shows a rule violation on the plan form', function (): void {
    inCenterPanel($this->tenant, $this->daiUser);

    Livewire::test(CreateDawahActivityPlan::class)
        ->fillForm(['title' => 'Open day', 'activity_type_id' => $this->type->id, 'objective' => 'x', 'planned_date' => now()->subDays(3)->toDateString()])
        ->call('create')
        ->assertHasFormErrors(['planned_date']);
});

it('records a daily activity from the activities screen', function (): void {
    inCenterPanel($this->tenant, $this->daiUser);

    Livewire::test(ManageDaiDailyActivities::class)
        ->callAction('create', ['activity_date' => now()->toDateString(), 'activity_type_id' => $this->type->id, 'title' => 'Lesson', 'started_at' => '08:00', 'ended_at' => '09:00'])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => DaiDailyActivity::query()->sole()->duration_minutes))->toBe(60);
});

it('opens the plan list to dawah.plans.view only', function (): void {
    $this->actingAs($this->head)->get('/center/DMM/dawah-plans')->assertOk();
    $this->actingAs(grantRole(memberOf($this->tenant), 'teacher', $this->tenant))->get('/center/DMM/dawah-plans')->assertForbidden();
});
