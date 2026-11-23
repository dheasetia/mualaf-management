<?php

declare(strict_types=1);

return [
    /* Host serving Filament's web font (Bunny Fonts) until fonts are bundled locally. */
    'font_host' => env('CSP_FONT_HOST', 'https://fonts.bunny.net'),

    /* A login from a browser not seen for the account in this many days triggers an email. */
    'known_device_days' => (int) env('KNOWN_DEVICE_DAYS', 90),

    /* Access review (13 §13.6): accounts unused for this many days are flagged. */
    'stale_account_days' => (int) env('STALE_ACCOUNT_DAYS', 90),
];
