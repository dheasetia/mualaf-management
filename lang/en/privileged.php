<?php

declare(strict_types=1);

return [
    'model' => 'privileged access', 'plural' => 'Privileged access',
    'help' => 'Access to one convert\'s data for a stated reason, approved by someone else, for a limited time. Every opening is audited.',
    'statuses' => ['pending' => 'Waiting for approval', 'rejected' => 'Rejected', 'active' => 'Active', 'expired' => 'Expired', 'revoked' => 'Revoked'],
    'scopes' => ['profile' => 'Profile', 'contacts' => 'Contact persons', 'identity' => 'Identity numbers', 'shahadah' => 'Shahadah', 'follow_ups' => 'Follow-up'],
    'fields' => [
        'office' => 'Center', 'identity_type' => 'Identity type', 'identity_number' => 'Identity number', 'reason' => 'Reason', 'scope' => 'Scope',
        'minutes' => 'Duration (minutes)', 'status' => 'Status', 'requested_by' => 'Requested by', 'expires_at' => 'Ends', 'note' => 'Note', 'requested_at' => 'Requested',
    ],
    'actions' => ['request' => 'Request access', 'approve' => 'Approve', 'reject' => 'Reject', 'revoke' => 'End access', 'open' => 'Open'],
    'no_permission_to_contact' => 'not agreed to be contacted',
    'view_notice' => 'Privileged access — this view is recorded in the audit log.',
    'notifications' => ['saved' => 'Saved.'],
    'errors' => [
        'reason_required' => 'A reason is required.',
        'scope_required' => 'Choose what you need to see.',
        'duration_invalid' => 'Choose 15 minutes to 24 hours.',
        'convert_not_found' => 'No convert is registered with this identity.',
        'self_approval' => 'Someone other than the requester must decide.',
        'not_pending' => 'This request has already been decided.',
        'not_active' => 'This access is not active.',
        'platform_only' => 'Privileged access is handled in Central.',
    ],
];
