<?php

declare(strict_types=1);

return [
    'locked' => [
        'message' => 'Sign-in for this account is temporarily blocked after repeated failed attempts. Try again in :minutes minutes.',
        'subject' => 'Sign-in to your account is temporarily blocked',
        'line' => ':attempts failed sign-in attempts were made on your account, so sign-in is blocked for :minutes minutes.',
        'ip' => 'IP address of the last attempt: :ip',
        'advice' => 'If this was not you, change your password and inform your center administrator.',
    ],
];
