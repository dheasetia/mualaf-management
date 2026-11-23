<?php

declare(strict_types=1);

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Critical test #11 (14-testing §14.3): two simultaneous requests for one convert, one refused.
it('keeps one open transfer per convert, also when two offices ask at once', function (): void {
    seedRbac();
    [$dammam, $riyadh, $jeddah] = [
        Tenant::factory()->active()->create(['code' => 'DMM']),
        Tenant::factory()->active()->create(['code' => 'RUH']),
        Tenant::factory()->active()->create(['code' => 'JED']),
    ];
    $iqamah = saudiId('255667788');
    ['convert' => $convert, 'toHead' => $riyadhHead] = transferSetup($dammam, $riyadh, $iqamah);
    $jeddahHead = grantRole(memberOf($jeddah), 'head_of_dawah', $jeddah);

    $first = inTenant($riyadh, fn () => app(RequestConvertTransfer::class)->handle(new IdentifierData(IdentityType::Iqamah, $iqamah), 'Moved', $riyadhHead));

    inTenant($jeddah, fn () => expectRuleViolation(fn () => app(RequestConvertTransfer::class)
        ->handle(new IdentifierData(IdentityType::Iqamah, $iqamah), 'Moved', $jeddahHead), 'identity_number'));

    // The database guard itself, as when the second insert races past every check.
    expect(fn () => DB::table('convert_transfer_requests')->insert([
        'uuid' => (string) Str::uuid(), 'convert_id' => $convert->id, 'from_tenant_id' => $dammam->id, 'to_tenant_id' => $jeddah->id,
        'requested_by' => $jeddahHead->id, 'reason' => 'race', 'requested_at' => now(), 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect($first->exists)->toBeTrue();
});
