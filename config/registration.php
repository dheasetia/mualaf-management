<?php

declare(strict_types=1);

return [
    /*
    | Witnesses recorded with every shahadah (open question #2: mandatory,
    | decided 23-11-2026): at least two witnesses.
    */
    'shahadah_min_witnesses' => (int) env('SHAHADAH_MIN_WITNESSES', 2),
    'shahadah_max_witnesses' => 5,

    /*
    | Import of historical converts (11 §11.5): rows per CSV file and the
    | largest file accepted, in kilobytes.
    */
    'import_max_rows' => (int) env('IMPORT_MAX_ROWS', 2000),
    'import_max_kilobytes' => 2048,
];
