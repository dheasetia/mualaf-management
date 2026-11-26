<?php

declare(strict_types=1);

return [
    'title' => 'Follow-up board',
    'actions' => [
        'register_shahadah' => 'Register new Muslim',
        'new_prospect' => 'New prospect',
        'follow_ups' => 'Follow-up schedule',
    ],
    'stats' => [
        'converts_active' => 'New Muslims',
        'converts_active_help' => 'Active new Muslims in this center',
        'prospects_open' => 'Prospects',
        'prospects_open_help' => 'Prospects whose follow-up is still open',
        'follow_ups_overdue' => 'Overdue follow-ups',
        'follow_ups_overdue_help' => 'Follow-ups past their time and not recorded',
        'shahadah_this_month' => 'Shahadah this month',
        'shahadah_this_month_help' => 'Verified since the start of the month',
        'as_of' => 'As of :date',
        'not_aggregated' => 'Statistics not computed yet',
    ],
    'my_follow_ups' => [
        'heading' => 'My follow-ups today',
        'description' => 'Overdue and due today.',
        'empty' => 'No follow-ups for today.',
        'time' => 'Time',
    ],
    'awaiting_verification' => [
        'heading' => 'Awaiting my verification',
        'description' => 'Shahadah registrations you may verify (not those you recorded or corrected).',
        'empty' => 'No registrations awaiting verification.',
        'registered_by' => 'Registered by',
    ],
    'year_summary' => [
        'heading' => ':year so far',
        'last_30_days' => 'Last 30 days: :count',
    ],
    'charts' => [
        'shahadah_heading' => 'Shahadah per month',
        'shahadah_description' => 'Verified over the last 12 months',
        'activity_heading' => 'Center activity',
        'activity_description' => 'Last 6 months',
    ],
    'operations' => [
        'heading' => 'Work in progress',
        'dais_active' => 'Active da\'i',
        'dais_active_help' => 'On duty',
        'classes_running' => 'Guidance classes running',
        'classes_running_help' => 'Open or in progress',
        'exams_upcoming' => 'Upcoming exams',
        'exams_upcoming_help' => 'In the next 30 days',
        'transfers_open' => 'Transfers in progress',
        'transfers_open_help' => 'From or to this center',
        'pickups_open' => 'Open pickup requests',
        'pickups_open_help' => 'Requested or assigned',
        'pilgrims_open' => 'Umrah and Hajj applications',
        'pilgrims_open_help' => 'Being processed',
    ],
];
