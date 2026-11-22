<?php

declare(strict_types=1);

return [
    /*
    | Weight of a "late" attendance in the attendance percentage
    | (09-data-model-guidance §9.4; 1.0 counts late as present).
    */
    'late_weight' => (float) env('GUIDANCE_LATE_WEIGHT', 1.0),
];
