<?php

declare(strict_types=1);

namespace App\Support\Text;

use Normalizer;

/**
 * Plain, indexed search form of a person's name (04-identity-privacy §4.4):
 * case, Latin accents, Arabic diacritics and tatweel are dropped, and Arabic
 * letter variants are folded, so "Muḥammad", "MUHAMMAD" and "مُحَمَّد" match their
 * plain spellings.
 */
final class NameSearchKey
{
    private const array ARABIC_FOLDS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي',
        'ؤ' => 'و',
        'ة' => 'ه',
    ];

    public static function make(string $name): string
    {
        // Arabic harakat, superscript alef and tatweel.
        $name = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $name) ?? $name;
        $name = strtr($name, self::ARABIC_FOLDS);

        // Latin accents: decompose, then drop the combining marks.
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_D);
        $name = preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $name : $decomposed) ?? $name;

        $name = mb_strtolower($name);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;

        return trim($name);
    }
}
