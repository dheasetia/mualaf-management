<?php

declare(strict_types=1);

return [
    /*
    | Witnesses recorded with every shahadah (open question #2: mandatory,
    | decided 23-11-2026): at least two witnesses.
    */
    'shahadah_min_witnesses' => (int) env('SHAHADAH_MIN_WITNESSES', 2),
    'shahadah_max_witnesses' => 5,
];
