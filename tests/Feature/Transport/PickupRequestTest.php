<?php

declare(strict_types=1);

use App\Domain\Converts\Models\ConvertContactPerson;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transport\Actions\PickupRequests;
use App\Domain\Transport\Enums\PickupStatus;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedRbac();
    $this->tenant = Tenant::factory()->active()->create();
    $this->officer = grantRole(memberOf($this->tenant), 'transportation_officer', $this->tenant);
    $this->in = fn (Closure $callback) => inTenant($this->tenant, $callback);
    $this->setup = ($this->in)(fn () => convertWithPickupPoint());
    $this->request = fn (array $data, $actor = null) => ($this->in)(fn () => app(PickupRequests::class)->request($this->setup['convert'], [
        'pickup_location_type' => 'residence', 'residence_id' => $this->setup['residence']->id,
        'pickup_at' => now()->addDay()->toIso8601String(), ...$data,
    ], $actor ?? $this->officer));
});

it('copies the pickup point from the convert\'s residence', function (): void {
    $pickup = ($this->request)(['contact_person_id' => $this->setup['contact']->id]);

    expect($pickup)
        ->status->toBe(PickupStatus::Requested)
        ->residence_id->toBe($this->setup['residence']->id)
        ->latitude->toBe('26.4207000')
        ->pickup_address->toContain('Camp 4, Block B');
});

it('refuses another convert\'s residence, a workplace without location, an empty custom point and past times', function (): void {
    $other = ($this->in)(fn () => convertWithPickupPoint());

    expectRuleViolation(fn () => ($this->request)(['residence_id' => $other['residence']->id]), 'pickup_location_type');
    expectRuleViolation(fn () => ($this->request)(['pickup_location_type' => 'workplace']), 'pickup_location_type');
    expectRuleViolation(fn () => ($this->request)(['pickup_location_type' => 'custom']), 'pickup_location_type');
    expectRuleViolation(fn () => ($this->request)(['pickup_at' => now()->subHour()->toIso8601String()]), 'pickup_at');
});

it('accepts only contact persons who agreed to be contacted', function (): void {
    ($this->in)(fn () => ConvertContactPerson::query()->whereKey($this->setup['contact']->id)->update(['permission_to_contact' => false]));

    expectRuleViolation(fn () => ($this->request)(['contact_person_id' => $this->setup['contact']->id]), 'contact_person_id');
});

it('needs transport.request and access to the convert\'s locations (an unassigned da\'i has neither view)', function (): void {
    expect(fn () => ($this->request)([], grantRole(memberOf($this->tenant), 'registration_officer', $this->tenant)))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->request)([], grantRole(memberOf($this->tenant), 'dai', $this->tenant)))->toThrow(AuthorizationException::class);
});
