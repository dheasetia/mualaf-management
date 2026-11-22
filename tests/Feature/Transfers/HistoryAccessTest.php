<?php

declare(strict_types=1);

use App\Domain\Converts\Actions\RecordConvertFollowUp;
use App\Domain\Converts\Data\IdentifierData;
use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Models\Convert;
use App\Domain\Dai\Data\FollowUpData;
use App\Domain\Dai\Enums\ContactMethod;
use App\Domain\Dai\Enums\FollowUpType;
use App\Domain\Dai\Models\DaiFollowUp;
use App\Domain\Dai\Services\FollowUpHistory;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Transfers\Actions\AcceptConvertTransfer;
use App\Domain\Transfers\Actions\RequestConvertTransfer;
use App\Domain\Transfers\Actions\ReviewConvertTransfer;
use Carbon\CarbonImmutable;

// Critical test #12 (14-testing §14.3).
it('lets the new office read the old office\'s follow-ups, and not the other way round', function (): void {
    seedRbac();
    [$dammam, $riyadh] = [Tenant::factory()->active()->create(['code' => 'DMM']), Tenant::factory()->active()->create(['code' => 'RUH'])];
    $iqamah = saudiId('266778899');
    ['convert' => $convert, 'fromHead' => $fromHead, 'toHead' => $toHead, 'toDai' => $toDai] = transferSetup($dammam, $riyadh, $iqamah);
    $note = fn (string $summary): FollowUpData => new FollowUpData(FollowUpType::Visit, ContactMethod::InPerson, CarbonImmutable::now()->subHour(), $summary);

    inTenant($dammam, fn () => app(RecordConvertFollowUp::class)->handle($convert, $note('Before transfer, in Dammam'), $fromHead));

    $request = inTenant($riyadh, fn () => app(RequestConvertTransfer::class)->handle(new IdentifierData(IdentityType::Iqamah, $iqamah), 'Moved', $toHead));
    inTenant($dammam, fn () => app(ReviewConvertTransfer::class)->approve($request, $fromHead));
    inTenant($riyadh, fn () => app(AcceptConvertTransfer::class)->handle($request, $toDai->id, $toHead));

    inTenant($riyadh, function () use ($convert, $note, $toHead): void {
        $moved = Convert::query()->findOrFail($convert->id);
        app(RecordConvertFollowUp::class)->handle($moved, $note('After transfer, in Riyadh'), $toHead);

        expect(app(FollowUpHistory::class)->forSubject($moved, $toHead)->pluck('summary')->all())
            ->toBe(['After transfer, in Riyadh', 'Before transfer, in Dammam']);
    });

    inTenant($dammam, function () use ($convert): void {
        expect(Convert::query()->find($convert->id))->toBeNull()
            ->and(DaiFollowUp::query()->where('subject_id', $convert->id)->pluck('summary')->all())->toBe(['Before transfer, in Dammam']);
    });
})->group('tenant-isolation');
