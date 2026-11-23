<?php

declare(strict_types=1);

use App\Domain\Converts\Enums\IdentityType;
use App\Domain\Converts\Exceptions\IdentityRuleViolation;
use App\Domain\Converts\Services\IdentityNumber;

it('accepts Saudi ID numbers only with the right first digit and Luhn check digit', function (IdentityType $type, string $input, ?string $expected): void {
    if ($expected === null) {
        expect(fn () => IdentityNumber::normalize($type, $input))->toThrow(IdentityRuleViolation::class);

        return;
    }

    expect(IdentityNumber::normalize($type, $input))->toBe($expected);
})->with([
    'national id' => [IdentityType::NationalId, '1000000008', '1000000008'],
    'iqamah with spaces and dashes' => [IdentityType::Iqamah, '2-000 000 006', '2000000006'],
    'iqamah in arabic-indic digits' => [IdentityType::Iqamah, '٢٠٠٠٠٠٠٠٠٦', '2000000006'],
    'iqamah given as national id' => [IdentityType::NationalId, '2000000006', null],
    'wrong check digit' => [IdentityType::Iqamah, '2000000007', null],
    'nine digits' => [IdentityType::Iqamah, '200000006', null],
    'passport lowercase with spaces' => [IdentityType::Passport, ' p 1234567 ', 'P1234567'],
    'passport with symbols' => [IdentityType::Passport, 'P12#45', null],
]);

it('shows only the last four characters', function (): void {
    expect(IdentityNumber::mask('0006'))->toBe('••••••0006');
});
