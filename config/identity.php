<?php

declare(strict_types=1);

return [
    /*
    | HMAC key for fingerprints of identity numbers and phone numbers
    | (04-identity-privacy §4.2). base64:-prefixed like APP_KEY. Changing it
    | requires re-fingerprinting stored values.
    */
    'hmac_key' => env('IDENTITY_HMAC_KEY'),
    'hmac_key_version' => (int) env('IDENTITY_HMAC_KEY_VERSION', 1),

    /*
    | Score from which a registration is held for duplicate review
    | (open question #10: proposed 0.85).
    */
    'duplicate_threshold' => (float) env('DUPLICATE_MATCH_THRESHOLD', 0.85),
];
