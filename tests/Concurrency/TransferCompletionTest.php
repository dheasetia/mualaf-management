<?php

declare(strict_types=1);

use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\AcceptConvertTransfer;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use App\Domain\Transfers\Models\ConvertTransferHistory;

// Critical test #3 (14-testing §14.3): a transfer cannot complete twice.
it('completes a transfer only once', function (): void {
    seedRbac();
    [$dammam, $riyadh] = [Tenant::factory()->active()->create(['code' => 'DMM']), Tenant::factory()->active()->create(['code' => 'RUH'])];
    $iqamah = saudiId('244556677');
    ['toHead' => $toHead, 'fromHead' => $fromHead, 'toDai' => $toDai] = transferSetup($dammam, $riyadh, $iqamah);

    $request = inTenant($riyadh, fn () => app(RequestConvertTransfer::class)->handle(new IdentifierData(IdentityType::Iqamah, $iqamah), 'Moved', $toHead));
    inTenant($dammam, fn () => app(ReviewConvertTransfer::class)->approve($request, $fromHead));
    inTenant($riyadh, fn () => app(AcceptConvertTransfer::class)->handle($request, $toDai->id, $toHead));

    inTenant($riyadh, fn () => expectRuleViolation(fn () => app(AcceptConvertTransfer::class)->handle($request, $toDai->id, $toHead), 'status'));

    expect(inTenant($riyadh, fn () => ConvertTransferHistory::query()->count()))->toBe(1);
});
