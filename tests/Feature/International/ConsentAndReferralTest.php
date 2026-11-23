<?php

declare(strict_types=1);

use App\Domain\FollowUp\Actions\RecordFollowUpStatus;
use App\Domain\International\Actions\Checklists;
use App\Domain\International\Actions\Consents;
use App\Domain\International\Actions\HomeCountryRecords;
use App\Domain\International\Actions\ReferralWorkflow;
use App\Domain\International\Enums\ChecklistItemCode;
use App\Domain\International\Enums\ChecklistStatus;
use App\Domain\International\Enums\ItemStatus;
use App\Domain\International\Enums\ReferralStatus;
use App\Domain\International\Models\ConvertHomeCountryContact;
use App\Domain\International\Models\PreDepartureChecklist;
use App\Domain\International\Services\ReferralPacket;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->philippines = Country::factory()->create();
    $this->tenant = Tenant::factory()->active()->create();
    $this->head = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->witness = grantRole(memberOf($this->tenant), 'head_of_dawah', $this->tenant);
    $this->convert = documentsConvert($this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->consent = fn (array $fields = ['name', 'islamic_name', 'contact'], array $extra = []) => ($this->in)(fn () => app(Consents::class)->record($this->convert, [
        'consent_type' => 'international_sharing', 'fields' => $fields, 'method' => 'written', ...$extra,
    ], $this->head));
    $this->referral = fn (array $scope = ['name', 'islamic_name']) => ($this->in)(fn () => app(ReferralWorkflow::class)->create($this->convert, [
        'destination_country_id' => $this->philippines->id, 'destination_city' => 'Davao', 'referral_reason' => 'Returning home', 'information_scope' => $scope,
    ], $this->head));
});

it('records consents with a scope, and a witness other than the recorder for verbal consent', function (): void {
    ($this->in)(function (): void {
        expectRuleViolation(fn () => app(Consents::class)->record($this->convert, ['consent_type' => 'international_sharing', 'fields' => [], 'method' => 'written'], $this->head), 'information_scope');
        expectRuleViolation(fn () => app(Consents::class)->record($this->convert, ['consent_type' => 'contact_family', 'method' => 'verbal_witnessed', 'witnessed_by' => $this->head->id], $this->head), 'witnessed_by');

        $verbal = app(Consents::class)->record($this->convert, ['consent_type' => 'contact_family', 'method' => 'verbal_witnessed', 'witnessed_by' => $this->witness->id], $this->head);
        expect($verbal->isActive())->toBeTrue();
    });
});

it('waits for consent covering every shared field and the destination before a referral is ready', function (): void {
    expect(($this->referral)()->status)->toBe(ReferralStatus::PendingConsent);

    ($this->consent)(['name']);
    expect(($this->referral)()->status)->toBe(ReferralStatus::PendingConsent);

    ($this->consent)(['name', 'islamic_name'], ['destination_country_id' => Country::factory()->create()->id]);
    expect(($this->referral)()->status)->toBe(ReferralStatus::PendingConsent);

    ($this->consent)(['name', 'islamic_name'], ['expires_at' => now()->subDay()->toDateTimeString()]);
    expect(($this->referral)()->status)->toBe(ReferralStatus::PendingConsent);

    $consent = ($this->consent)(['name', 'islamic_name', 'contact'], ['destination_country_id' => $this->philippines->id]);
    $referral = ($this->referral)();
    expect($referral)->status->toBe(ReferralStatus::Ready)->consent_record_id->toBe($consent->id);
});

it('lets only international.referrals.send send, and closes referrals when their consent is withdrawn', function (): void {
    $consent = ($this->consent)();
    $referral = ($this->referral)();
    $dai = grantRole(memberOf($this->tenant), 'dai', $this->tenant);

    ($this->in)(function () use ($consent, $referral, $dai): void {
        expect(fn () => app(ReferralWorkflow::class)->send($referral, $dai))->toThrow(AuthorizationException::class);
        app(ReferralWorkflow::class)->send($referral, $this->head);

        expectRuleViolation(fn () => app(Consents::class)->withdraw($consent, '', $this->head), 'reason');
        app(Consents::class)->withdraw($consent, 'Changed their mind', $this->head);
        expectRuleViolation(fn () => app(Consents::class)->withdraw($consent->refresh(), 'Again', $this->head), 'status');

        expect($referral->refresh())->status->toBe(ReferralStatus::Closed)->close_reason->toBe('consent_withdrawn');
    });
});

it('shares only the fields in scope, and only contacts who agreed to be contacted', function (): void {
    ($this->in)(function (): void {
        $records = app(HomeCountryRecords::class);
        $records->saveContact($this->convert, null, ['country_id' => $this->philippines->id, 'contact_name' => 'Hidden Brother', 'relationship' => 'sibling', 'phone' => '+639171111111', 'is_primary' => true], $this->head);
        $records->saveContact($this->convert, null, ['country_id' => $this->philippines->id, 'contact_name' => 'Imam Yusuf', 'relationship' => 'local_dai', 'phone' => '+639172222222', 'permission_to_contact' => true], $this->head);

        expect(ConvertHomeCountryContact::query()->where('permission_to_contact', true)->count())->toBe(1);
    });
    ($this->consent)(['islamic_name', 'contact']);
    $referral = ($this->referral)(['islamic_name', 'contact']);

    $packet = ($this->in)(fn () => app(ReferralPacket::class)->for($referral));

    expect($packet)->toBe(['islamic_name' => 'Dawud', 'contact' => 'Imam Yusuf 639172222222'])
        ->and(json_encode($packet))->not->toContain('Daniel Mendoza')->not->toContain('Hidden Brother');
});

it('opens a pre-departure checklist when the convert returns home, ticked by the referral steps', function (): void {
    ($this->in)(fn () => app(RecordFollowUpStatus::class)->handle($this->convert, ['status' => 'returned_home', 'last_known_country_id' => $this->philippines->id], $this->head));
    ($this->in)(fn () => app(RecordFollowUpStatus::class)->handle($this->convert, ['status' => 'returned_home'], $this->head));

    $checklist = ($this->in)(fn () => PreDepartureChecklist::query()->sole());
    expect($checklist)->destination_country_id->toBe($this->philippines->id)
        ->and(($this->in)(fn () => $checklist->items()->count()))->toBe(count(ChecklistItemCode::cases()));

    ($this->consent)();
    $referral = ($this->referral)();
    ($this->in)(function () use ($referral, $checklist): void {
        app(ReferralWorkflow::class)->send($referral, $this->head);
        app(ReferralWorkflow::class)->acknowledge($referral->refresh(), $this->head);

        $done = $checklist->items()->where('status', ItemStatus::Done)->pluck('item_code')->all();
        expect($done)->toEqualCanonicalizing([ChecklistItemCode::ReferralSent, ChecklistItemCode::AcknowledgmentReceived])
            ->and($checklist->refresh()->referral_id)->toBe($referral->id);

        expectRuleViolation(fn () => app(Checklists::class)->complete($checklist, $this->head), 'status');
        $checklist->items()->where('status', ItemStatus::Pending)->get()->each(fn ($item) => app(Checklists::class)->mark($item, ItemStatus::NotApplicable, null, $this->head));
        expect(app(Checklists::class)->complete($checklist, $this->head)->status)->toBe(ChecklistStatus::Completed);
    });
});
