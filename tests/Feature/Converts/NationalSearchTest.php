<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Exceptions\IdentityRuleViolation;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Converts\Services\NationalSearch;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Center\Pages\NationalIdentitySearch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    $this->dammam = Tenant::factory()->active()->create(['code' => 'DMM']);
    $this->riyadh = Tenant::factory()->active()->create(['code' => 'RUH', 'name_ar' => 'المركز الإسلامي بالرياض']);
    $this->iqamah = saudiId('234567891');

    $this->registerIn = function (Tenant $tenant, DisclosureLevel $level = DisclosureLevel::Normal): Convert {
        return inTenant($tenant, function () use ($level): Convert {
            $convert = Convert::factory()->create(['full_name' => 'Abdullah Reyes', 'disclosure_risk_level' => $level]);
            app(IdentityRegistry::class)->add($convert, new IdentifierData(IdentityType::Iqamah, $this->iqamah), User::factory()->create());

            return $convert;
        });
    };
    $this->searchAs = fn (User $user, string $number): mixed => inTenant($this->dammam, fn () => app(NationalSearch::class)
        ->search($user, new IdentifierData(IdentityType::Iqamah, $number)));

    $this->registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);
    $this->head = grantRole(memberOf($this->dammam), 'head_of_dawah', $this->dammam);
});

it('is open to holders of identity.national_search only', function (): void {
    ($this->searchAs)(grantRole(memberOf($this->dammam), 'dai', $this->dammam), $this->iqamah);
})->throws(AuthorizationException::class);

it('reports an unregistered identity', function (): void {
    expect(($this->searchAs)($this->registrar, $this->iqamah)->found)->toBeFalse();
});

it('shows the office and initials of a convert registered elsewhere', function (): void {
    ($this->registerIn)($this->riyadh);

    $result = ($this->searchAs)($this->registrar, $this->iqamah);

    expect($result)
        ->found->toBeTrue()
        ->inOwnOffice->toBeFalse()
        ->officeCode->toBe('RUH')
        ->displayName->toBe('A. R.');
})->group('tenant-isolation');

it('shows the full name only for normal disclosure and to holders of converts.transfer', function (DisclosureLevel $level, string $expected): void {
    ($this->registerIn)($this->riyadh, $level);

    expect(($this->searchAs)($this->head, $this->iqamah)->displayName)->toBe($expected);
})->with([
    'normal' => [DisclosureLevel::Normal, 'Abdullah Reyes'],
    'restricted' => [DisclosureLevel::Restricted, 'A. R.'],
]);

it('withholds office and name for high-confidentiality converts', function (): void {
    ($this->registerIn)($this->riyadh, DisclosureLevel::HighConfidentiality);

    expect(($this->searchAs)($this->head, $this->iqamah))
        ->found->toBeTrue()
        ->withheld->toBeTrue()
        ->officeCode->toBeNull()
        ->displayName->toBeNull();
});

it('audits every search without the number', function (): void {
    ($this->registerIn)($this->riyadh);
    ($this->searchAs)($this->registrar, $this->iqamah);

    $entry = AuditLog::query()->where('action', 'identity.national_search')->sole();

    expect($entry->actor_id)->toBe($this->registrar->id)
        ->and($entry->new_values_redacted['found'])->toBeTrue()
        ->and(json_encode($entry))->not->toContain($this->iqamah)
        ->and(AuditLog::query()->where('action', 'tenancy.bypassed')->exists())->toBeTrue();
});

it('allows 30 searches per hour per user; invalid numbers do not count', function (): void {
    expect(fn () => ($this->searchAs)($this->registrar, '2000000007'))->toThrow(IdentityRuleViolation::class);

    foreach (range(1, NationalSearch::MAX_PER_HOUR) as $attempt) {
        ($this->searchAs)($this->registrar, $this->iqamah);
    }

    expect(fn () => ($this->searchAs)($this->registrar, $this->iqamah))
        ->toThrow(fn (IdentityRuleViolation $violation) => expect($violation->getMessage())->toContain('60'));

    expect(($this->searchAs)($this->head, $this->iqamah)->found)->toBeFalse();
});

it('searches from the center panel and clears the number afterwards', function (): void {
    ($this->registerIn)($this->riyadh);
    inCenterPanel($this->dammam, $this->registrar);

    Livewire::test(NationalIdentitySearch::class)
        ->fillForm(['identity_type' => 'iqamah', 'identity_number' => $this->iqamah])
        ->call('search')
        ->assertHasNoFormErrors()
        ->assertSee('RUH')
        ->assertSet('data.identity_number', null);

    $this->actingAs(grantRole(memberOf($this->dammam), 'dai', $this->dammam))
        ->get('/center/DMM/national-identity-search')
        ->assertForbidden();
});
