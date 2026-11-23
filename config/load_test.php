<?php

declare(strict_types=1);

return [
    /* 15 §15.1: size of the synthetic office created by LoadTestSeeder (staging only). */
    'dais' => (int) env('LOAD_DAIS', 30),
    'converts' => (int) env('LOAD_CONVERTS', 5000),
    'prospects' => (int) env('LOAD_PROSPECTS', 5000),
];
