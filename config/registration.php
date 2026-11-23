<?php

declare(strict_types=1);

return [
    /*
    | Witnesses recorded with every shahadah (open question #2: mandatory,
    | decided 23-11-2026). The number is not decided yet; two is the default.
    */
    'shahadah_min_witnesses' => (int) env('SHAHADAH_MIN_WITNESSES', 2),
    'shahadah_max_witnesses' => 5,
];
