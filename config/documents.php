<?php

declare(strict_types=1);

return [
    /* Private disk for uploaded files and generated letters (object storage in production). */
    'disk' => env('DOCUMENTS_DISK', 'local'),

    /* 13 §13.1: content-checked whitelist and size limit. */
    'allowed_mime_types' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    'max_bytes' => 10 * 1024 * 1024,

    /* 13 §13.1: ClamAV (clamd) scan before a file can be verified or downloaded. */
    'antivirus' => [
        'enabled' => (bool) env('CLAMAV_ENABLED', false),
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],

    /* Minutes a signed download link stays valid. */
    'download_link_minutes' => 5,
];
