<?php

declare(strict_types=1);

return [
    /* Private disk for certificate PDFs (object storage in production). */
    'disk' => env('CERTIFICATES_DISK', 'local'),
];
