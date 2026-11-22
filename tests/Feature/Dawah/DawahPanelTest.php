<?php

declare(strict_types=1);

use App\Domain\Dai\Models\Dai;
use App\Domain\Dawah\Actions\ManagePlans;
use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Enums\PlanStatus;
use App\Domain\Dawah\Models\DaiDailyActivity;
use App\Domain\Dawah\Models\DawahActivityPlan;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Platform\Models\BudgetCategory;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\DaiActivities\Pages\ManageDaiDailyActivities;
use App\Filament\Center\Resources\DawahMaterials\Pages\ManageDawahMaterials;
use App\Filament\Center\Resources\DawahPlans\Pages\CreateDawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\Pages\ViewDawahActivityPlan;
use App\Filament\Center\Resources\DawahPlans\RelationManagers\BudgetLinesRelationManager;
use Filament\Actions\Testing\TestAction;
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

it('lets the plan\'s da\'i add budget lines on the plan page', function (): void {
    $category = BudgetCategory::factory()->create();
    inCenterPanel($this->tenant, $this->daiUser);
    $plan = inTenant($this->tenant, fn () => app(ManagePlans::class)->save(null, [
        'title' => 'Open day', 'activity_type_id' => $this->type->id, 'objective' => 'x', 'planned_date' => now()->addWeek()->toDateString(),
    ], $this->daiUser));

    Livewire::test(BudgetLinesRelationManager::class, ['ownerRecord' => $plan, 'pageClass' => ViewDawahActivityPlan::class])
        ->callAction(TestAction::make('addLine')->table(), ['budget_category_id' => $category->id, 'description' => 'Tea', 'quantity' => '3', 'unit_cost' => '10'])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => $plan->refresh()->estimated_budget))->toBe('30.00');
});

it('lists dawah materials for da\'i and creates a draft', function (): void {
    inCenterPanel($this->tenant, $this->daiUser);

    Livewire::test(ManageDawahMaterials::class)
        ->callAction('create', ['title' => 'Leaflet', 'material_type' => 'leaflet', 'language_id' => Language::factory()->create()->id])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => DawahMaterial::query()->sole()->status))->toBe(MaterialStatus::Draft);
});
