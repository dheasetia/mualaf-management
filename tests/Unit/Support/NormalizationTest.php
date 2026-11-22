<?php

declare(strict_types=1);

use App\Support\Identity\PhoneNumber;
use App\Support\Text\NameSearchKey;

it('normalises the ways a phone number is typed to one form', function (?string $input, ?string $expected): void {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'international with spaces' => ['+966 50-123 4567', '966501234567'],
    'double zero prefix' => ['00966501234567', '966501234567'],
    'saudi local' => ['0501234567', '966501234567'],
    'saudi local without zero' => ['501234567', '966501234567'],
    'arabic-indic digits' => ['٠٥٠١٢٣٤٥٦٧', '966501234567'],
    'foreign number' => ['+63 917 123 4567', '639171234567'],
    'too short' => ['12345', null],
    'empty' => [null, null],
]);

it('folds names to one search key', function (string $input, string $expected): void {
    expect(NameSearchKey::make($input))->toBe($expected);
})->with([
    'latin case and accents' => ['  José  MUḤAMMAD ', 'jose muhammad'],
    'punctuation' => ["O'Brien-Smith", 'o brien smith'],
    'arabic diacritics and tatweel' => ['مُحَمَّـد', 'محمد'],
    'arabic letter variants' => ['أسامة إبراهيم', 'اسامه ابراهيم'],
]);
