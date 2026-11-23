<?php

declare(strict_types=1);

use App\Domain\FollowUp\Models\ConvertFollowUpSchedule;
use App\Domain\FollowUp\Models\ConvertFollowUpStatus;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Resources\Converts\Pages\ViewConvert;
use App\Filament\Center\Resources\Converts\RelationManagers\FollowUpStatusesRelationManager;
use App\Filament\Center\Resources\FollowUpSchedules\Pages\ListFollowUpSchedules;
use Database\Seeders\FollowUpScheduleTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    $this->seed(FollowUpScheduleTemplateSeeder::class);
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->tenant = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->head = withAuthenticator(grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant));
    $this->convert = documentsConvert($this->tenant);
});

it('updates the follow-up status from the convert page', function (): void {
    inCenterPanel($this->tenant, $this->head);

    Livewire::test(FollowUpStatusesRelationManager::class, ['ownerRecord' => $this->convert, 'pageClass' => ViewConvert::class])
        ->callAction(TestAction::make('recordStatus')->table(), ['status' => 'stable', 'follow_up_priority' => 'normal'])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => ConvertFollowUpStatus::query()->sole()->status->value))->toBe('stable');
});

it('lists overdue follow-ups and records the contact from the schedule', function (): void {
    inCenterPanel($this->tenant, $this->head);
    $schedule = inTenant($this->tenant, fn () => ConvertFollowUpSchedule::query()->orderBy('scheduled_at')->firstOrFail());
    $this->travel(8)->days();

    Livewire::test(ListFollowUpSchedules::class)
        ->assertCanSeeTableRecords([$schedule])
        ->callAction(TestAction::make('record')->table($schedule), ['contact_method' => 'visit', 'contacted_person_type' => 'convert', 'contact_result' => 'reached', 'confidentiality_level' => 'normal'])
        ->assertHasNoActionErrors();

    expect(inTenant($this->tenant, fn () => $schedule->refresh()->status->value))->toBe('completed');
});
