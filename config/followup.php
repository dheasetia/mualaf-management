<?php

declare(strict_types=1);

return [
    /* PRD §31: days without a long-term contact before a follow-up is suggested to the da'i. */
    'quiet_days' => (int) env('FOLLOW_UP_QUIET_DAYS', 90),
];
