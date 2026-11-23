<?php

declare(strict_types=1);

use App\Domain\Examinations\Services\ScoreCalculator;

it('weights each component by its share of the maximum', function (): void {
    // Written 40/50 (weight 2) and oral 18/20 (weight 1): (0.8×2 + 0.9×1) / 3 × 100.
    expect(ScoreCalculator::finalScore([
        ['score' => 40, 'max_score' => 50, 'weight' => 2],
        ['score' => 18, 'max_score' => 20, 'weight' => 1],
    ], 100))->toBe(83.33);
});

it('caps a component at its maximum and handles no weight', function (): void {
    expect(ScoreCalculator::finalScore([['score' => 60, 'max_score' => 50, 'weight' => 1]], 100))->toBe(100.0)
        ->and(ScoreCalculator::finalScore([], 100))->toBe(0.0);
});
