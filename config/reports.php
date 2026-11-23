<?php

declare(strict_types=1);

return [
    /* Private disk for export files (13 §13.1). */
    'disk' => env('REPORTS_DISK', 'local'),

    /* Hours an export file is kept (11 §11.7 proposal: 24 hours). */
    'export_hours' => (int) env('EXPORT_RETENTION_HOURS', 24),
];
