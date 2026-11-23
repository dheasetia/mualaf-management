<?php

declare(strict_types=1);

return [

    /*
    | PDF rendering (13-infrastructure-security §13.2): Gotenberg on the internal
    | network, never exposed publicly.
    */
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://127.0.0.1:3000'),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 120),
    ],

    /*
    | WhatsApp Business Platform (Cloud API), open question #9. Off until the
    | Meta account, phone number and approved message template exist. The
    | template takes one body parameter: the alert title, generic text with
    | no personal data. Languages map the user's locale to Meta's codes.
    */
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'template' => env('WHATSAPP_TEMPLATE', 'office_alert'),
        'languages' => ['ar' => 'ar', 'en' => 'en'],
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
