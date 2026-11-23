<?php

declare(strict_types=1);

return [
    /*
    | Days the current office has to answer a convert transfer request before
    | it is escalated to Central (open question #4: proposed 7).
    */
    'sla_days' => (int) env('TRANSFER_SLA_DAYS', 7),
];
