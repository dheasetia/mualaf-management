<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Reports',
    'office_dashboard' => 'Office dashboard',
    'national_dashboard' => 'National dashboard',
    'exports' => ['model' => 'export', 'plural' => 'Exports'],
    'metrics' => [
        'shahadah_verified' => 'Conversions (verified)', 'prospects_created' => 'New prospects', 'follow_ups_recorded' => 'Follow-ups recorded',
        'enrollments_completed' => 'Guidance completed', 'certificates_issued' => 'Certificates issued', 'plans_completed' => 'Activities held',
        'submissions_completed' => 'Submissions completed', 'converts_active' => 'Active converts', 'prospects_open' => 'Open prospects',
        'follow_ups_overdue' => 'Overdue follow-ups', 'converts_total' => 'Converts registered nationally',
    ],
    'periods' => ['last_30_days' => 'Last 30 days', 'this_year' => 'This year', 'now' => 'Now'],
    'labels' => [
        'monthly_conversions' => 'Conversions per month', 'by_office' => 'Conversions by office this year', 'by_nationality' => 'Top nationalities this year',
        'month' => 'Month', 'office' => 'Office', 'country' => 'Country', 'count' => 'Count',
        'last_aggregated' => 'Figures up to :date (updated nightly).', 'not_aggregated' => 'No figures yet: statistics are aggregated nightly.',
    ],
    'export_types' => ['converts' => 'Convert list', 'shahadah_statistics' => 'Monthly conversion statistics'],
    'export_statuses' => ['queued' => 'Queued', 'processing' => 'Processing', 'ready' => 'Ready', 'failed' => 'Failed', 'expired' => 'Expired'],
    'fields' => [
        'export_type' => 'Type', 'reason' => 'Reason', 'from' => 'From', 'to' => 'To', 'status' => 'Status', 'rows' => 'Rows',
        'requested_at' => 'Requested', 'expires_at' => 'Available until', 'downloads' => 'Downloads',
    ],
    'actions' => ['request' => 'Request export', 'download' => 'Download'],
    'notifications' => [
        'requested' => 'Export requested',
        'password' => 'Keep this password; it is shown only once and is needed to open the file: :password',
    ],
    'errors' => [
        'reason_required' => 'Give the reason for exporting personal data.',
        'too_many_exports' => 'Export limit reached; try again in :minutes minutes.',
        'office_only' => 'Personal data is exported from an office, not from Central.',
        'not_downloadable' => 'This export is not available for download.',
    ],
];
