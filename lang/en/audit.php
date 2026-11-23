<?php

declare(strict_types=1);

return [
    'model' => 'audit entry',
    'plural' => 'Audit log',
    'navigation_group' => 'Security',
    'platform' => 'Platform',
    'fields' => [
        'created_at' => 'Time',
        'action' => 'Action',
        'actor' => 'Actor',
        'actor_type' => 'Actor type',
        'tenant' => 'Center',
        'subject' => 'Subject',
        'privileged_grant' => 'Privileged access grant',
        'ip_address' => 'IP address',
        'user_agent' => 'Browser',
        'request_id' => 'Request ID',
        'old_values' => 'Old values',
        'new_values' => 'New values',
    ],
    'actor_types' => [
        'user' => 'User',
        'system' => 'System',
        'job' => 'Background job',
    ],
    'filters' => [
        'from' => 'From',
        'until' => 'Until',
    ],
];
