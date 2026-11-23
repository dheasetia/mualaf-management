<?php

declare(strict_types=1);

use App\Support\Logging\RedactPersonalData;

it('masks emails, phone numbers and identity numbers in log text', function (string $input, string $expected): void {
    expect(RedactPersonalData::redact($input))->toBe($expected);
})->with([
    'email' => ['Lockout for ahmad.r@example.com', 'Lockout for [email]'],
    'iqamah' => ['Duplicate identity 2123456789 found', 'Duplicate identity [identity] found'],
    'saudi mobile' => ['Call 0501234567 now', 'Call [phone] now'],
    'international' => ['WhatsApp +63 917 123 4567', 'WhatsApp [phone]'],
    'request id kept' => ['Request 01HZX8K2Q9 failed', 'Request 01HZX8K2Q9 failed'],
    'short numbers kept' => ['Retry 3 of 5 after 120 seconds', 'Retry 3 of 5 after 120 seconds'],
]);
