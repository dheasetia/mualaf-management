<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Services;

/**
 * Final score of an attempt (09 §9.5): each component's score as a share of
 * its maximum, weighted, scaled to the exam's maximum score.
 */
final class ScoreCalculator
{
    /**
     * @param  list<array{score: float, max_score: float, weight: float}>  $components
     */
    public static function finalScore(array $components, float $examMaximum): float
    {
        $totalWeight = array_sum(array_column($components, 'weight'));

        if ($totalWeight <= 0.0) {
            return 0.0;
        }

        $weighted = 0.0;

        foreach ($components as $component) {
            $share = $component['max_score'] > 0 ? min($component['score'], $component['max_score']) / $component['max_score'] : 0.0;
            $weighted += $share * $component['weight'];
        }

        return round($weighted / $totalWeight * $examMaximum, 2);
    }
}
