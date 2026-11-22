<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Exceptions\ConvertAlreadyRegistered;
use App\Domain\Converts\Exceptions\ConvertTenantImmutable;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertIdentifier;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Platform\Models\Country;
use App\Domain\Tenancy\Exceptions\MissingTenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedRbac();
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);
    [$this->dammam, $this->riyadh] = Tenant::factory()->active()->count(2)->create();
    $this->actor = User::factory()->create();
    $this->registry = app(IdentityRegistry::class);
    $this->iqamah = saudiId('200000001');
});

function convertIn(Tenant $tenant): Convert
{
    return inTenant($tenant, fn (): Convert => Convert::factory()->create());
}

it('stores the number encrypted with its last four digits and an audit entry without it', function (): void {
    $convert = convertIn($this->dammam);

    $identifier = inTenant($this->dammam, fn () => $this->registry->add($convert, new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor, primary: true));

    $row = DB::table('convert_identifiers')->where('id', $identifier->id)->first();
    expect($row->identity_number_encrypted)->not->toContain($this->iqamah)
        ->and($row->identity_last4)->toBe(substr($this->iqamah, -4))
        ->and(strlen($row->identity_fingerprint))->toBe(32)
        ->and(json_encode(AuditLog::query()->where('action', 'identity.added')->sole()->new_values_redacted))->not->toContain($this->iqamah);
});

it('refuses a second convert with the same identity, in any office and however it is typed', function (): void {
    inTenant($this->dammam, fn () => $this->registry->add(convertIn($this->dammam), new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor));

    $typedDifferently = substr($this->iqamah, 0, 3).'-'.substr($this->iqamah, 3);

    inTenant($this->riyadh, fn () => $this->registry->add(convertIn($this->riyadh), new IdentifierData(IdentityType::Iqamah, $typedDifferently), $this->actor));
})->throws(ConvertAlreadyRegistered::class);

it('frees the identity once its identifier is replaced, and keeps passports apart by issuing country', function (): void {
    $first = inTenant($this->dammam, fn () => $this->registry->add(convertIn($this->dammam), new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor));
    DB::table('convert_identifiers')->where('id', $first->id)->update(['replaced_at' => now(), 'replaced_reason' => 'Data correction']);

    $again = inTenant($this->riyadh, fn () => $this->registry->add(convertIn($this->riyadh), new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor));

    [$philippines, $india] = Country::factory()->count(2)->create();
    $convert = convertIn($this->dammam);
    inTenant($this->dammam, function () use ($convert, $philippines, $india): void {
        $this->registry->add($convert, new IdentifierData(IdentityType::Passport, 'P1234567', $philippines->id), $this->actor);
        $this->registry->add($convert, new IdentifierData(IdentityType::Passport, 'P1234567', $india->id), $this->actor);
    });

    expect($again->exists)->toBeTrue()
        ->and(inTenant($this->dammam, fn () => ConvertIdentifier::query()->where('convert_id', $convert->id)->count()))->toBe(2);
});

it('keeps exactly one primary identifier per convert', function (): void {
    $convert = convertIn($this->dammam);

    inTenant($this->dammam, function () use ($convert): void {
        $this->registry->add($convert, new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor, primary: true);
        $this->registry->add($convert, new IdentifierData(IdentityType::Passport, 'P7654321', Country::factory()->create()->id), $this->actor, primary: true);

        expect(ConvertIdentifier::query()->where('convert_id', $convert->id)->where('is_primary', true)->sole()->identity_type)
            ->toBe(IdentityType::Passport);
    });
});

it('reveals the full number only with identity.reveal and audits it', function (): void {
    $convert = convertIn($this->dammam);
    $identifier = inTenant($this->dammam, fn () => $this->registry->add($convert, new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor));
    $verifier = grantRole(memberOf($this->dammam), 'shahadah_verifier', $this->dammam);
    $registrar = grantRole(memberOf($this->dammam), 'registration_officer', $this->dammam);

    inTenant($this->dammam, function () use ($identifier, $verifier, $registrar): void {
        expect($this->registry->reveal($identifier, $verifier))->toBe($this->iqamah)
            ->and($identifier->masked())->toBe('••••••'.substr($this->iqamah, -4))
            ->and(fn () => $this->registry->reveal($identifier, $registrar))->toThrow(AuthorizationException::class);
    });

    expect(AuditLog::query()->where('action', 'identity.revealed')->sole()->actor_id)->toBe($verifier->id);
});

it('shows converts and their identifiers only to their current office', function (): void {
    $riyadhConvert = convertIn($this->riyadh);
    inTenant($this->riyadh, fn () => $this->registry->add($riyadhConvert, new IdentifierData(IdentityType::Iqamah, $this->iqamah), $this->actor));

    inTenant($this->dammam, function () use ($riyadhConvert): void {
        expect(Convert::query()->find($riyadhConvert->id))->toBeNull()
            ->and(ConvertIdentifier::query()->count())->toBe(0);
    });

    expect(fn () => Convert::query()->count())->toThrow(MissingTenantContext::class);
})->group('tenant-isolation');

it('never moves a convert between offices through an ordinary save', function (string $column): void {
    $convert = convertIn($this->dammam);

    inTenant($this->dammam, fn () => $convert->forceFill([$column => $this->riyadh->id])->save());
})->with(['origin_tenant_id', 'current_tenant_id'])->throws(ConvertTenantImmutable::class);
