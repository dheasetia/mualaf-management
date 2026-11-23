<?php

declare(strict_types=1);

return [
    'profile' => [
        'phone' => 'Mobile number',
        'whatsapp_opt_in' => 'Send my alerts on WhatsApp too',
        'whatsapp_help' => 'Short alerts without personal data (for example "New transfer request"), in your language. Details stay in the platform.',
        'invalid_phone' => 'Enter a valid mobile number.',
    ],
    'new_device' => [
        'subject' => 'New sign-in to your account',
        'line' => 'Your account was signed in from a browser we have not seen recently, at :time.',
        'details' => 'IP address: :ip — browser: :browser',
    ],
    'access_review' => [
        'title' => 'Access review',
        'help' => 'Staff of this center with their roles. Review it regularly: remove roles that are no longer needed and accounts that are not used.',
        'name' => 'Name', 'email' => 'Email', 'roles' => 'Roles', 'mfa' => 'Authenticator', 'last_login' => 'Last sign-in', 'never' => 'Never',
        'flags' => 'Attention', 'missing_mfa' => 'Privileged role without authenticator', 'stale' => 'Not used for :days days',
    ],
    'locked' => [
        'message' => 'Sign-in for this account is temporarily blocked after repeated failed attempts. Try again in :minutes minutes.',
        'subject' => 'Sign-in to your account is temporarily blocked',
        'line' => ':attempts failed sign-in attempts were made on your account, so sign-in is blocked for :minutes minutes.',
        'ip' => 'IP address of the last attempt: :ip',
        'advice' => 'If this was not you, change your password and inform your center administrator.',
    ],
    'mfa_required' => [
        'title' => 'Two-factor authentication required',
        'body' => 'Your role in this center requires two-factor authentication. Enable an authenticator app on your profile page to continue.',
    ],
];
