<?php

declare(strict_types=1);

use App\Support\Text\NameSimilarity;

it('scores the same person written differently as similar', function (string $a, string $b): void {
    expect(NameSimilarity::score($a, $b))->toBeGreaterThanOrEqual(0.85);
})->with([
    'identical' => ['Jose Santos', 'Jose Santos'],
    'case and accents' => ['JOSÉ SANTOS', 'jose santos'],
    'word order' => ['Santos Jose', 'Jose Santos'],
    'missing middle name' => ['Jose Reyes Santos', 'Jose Santos'],
    'typo' => ['Jose Santoz', 'Jose Santos'],
    'arabic variants' => ['أسامة إبراهيم', 'اسامه ابراهيم'],
]);

it('scores different people as dissimilar', function (string $a, string $b): void {
    expect(NameSimilarity::score($a, $b))->toBeLessThan(0.85);
})->with([
    'different names' => ['Jose Santos', 'Maria Cruz'],
    'shared first name only' => ['Muhammad Rahman', 'Muhammad Iqbal'],
    'single shared word' => ['Muhammad', 'Muhammad Abdullah Khan'],
]);
