<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * Similarity of two names in [0, 1] on their search keys (NameSearchKey), for
 * duplicate screening (04-identity-privacy §4.4). Tolerates word order
 * ("Santos Jose" vs "Jose Santos"), a missing middle name and small typos.
 */
final class NameSimilarity
{
    public static function score(string $a, string $b): float
    {
        $tokensA = self::tokens($a);
        $tokensB = self::tokens($b);

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $sortedA = implode(' ', $tokensA);
        $sortedB = implode(' ', $tokensB);

        // Whole name after sorting the words, and the shorter name's words
        // matched one by one against the longer name's best word.
        return max(self::ratio($sortedA, $sortedB), self::tokenCoverage($tokensA, $tokensB));
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $tokens = array_values(array_filter(explode(' ', NameSearchKey::make($name)), static fn (string $token): bool => $token !== ''));
        sort($tokens);

        return $tokens;
    }

    /**
     * Words of the shorter name found (approximately) in the longer one,
     * penalised when the longer one has many more words.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function tokenCoverage(array $a, array $b): float
    {
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        $total = 0.0;

        foreach ($short as $token) {
            $best = 0.0;

            foreach ($long as $candidate) {
                $best = max($best, self::ratio($token, $candidate));
            }

            $total += $best;
        }

        $coverage = $total / count($short);
        $lengthPenalty = count($short) / count($long);

        // A single matching word ("Muhammad") must not look like a full match.
        return $coverage * (0.7 + 0.3 * $lengthPenalty) * (count($short) === 1 ? 0.8 : 1.0);
    }

    /** 1 - normalised Levenshtein distance, on characters (multibyte safe). */
    private static function ratio(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $charsA = mb_str_split($a);
        $charsB = mb_str_split($b);
        $lengthA = count($charsA);
        $lengthB = count($charsB);

        if ($lengthA === 0 || $lengthB === 0) {
            return 0.0;
        }

        $previous = range(0, $lengthB);

        for ($i = 1; $i <= $lengthA; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $lengthB; $j++) {
                $cost = $charsA[$i - 1] === $charsB[$j - 1] ? 0 : 1;
                $current[$j] = min($previous[$j] + 1, $current[$j - 1] + 1, $previous[$j - 1] + $cost);
            }

            $previous = $current;
        }

        return 1.0 - $previous[$lengthB] / max($lengthA, $lengthB);
    }
}
