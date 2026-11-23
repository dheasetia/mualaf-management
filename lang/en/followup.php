<?php

declare(strict_types=1);

return [
    'statuses' => ['plural' => 'Follow-up status'],
    'notes' => ['model' => 'long-term follow-up', 'plural' => 'Long-term follow-up'],
    'schedules' => ['model' => 'follow-up', 'plural' => 'Follow-up schedule'],
    'states' => [
        'active_guidance' => 'In guidance', 'stable' => 'Stable', 'needs_follow_up' => 'Needs follow-up',
        'needs_community_connection' => 'Needs community connection', 'temporarily_unreachable' => 'Temporarily unreachable',
        'lost_contact' => 'Lost contact', 'returned_home' => 'Returned home', 'followed_abroad' => 'Followed abroad',
    ],
    'activity_levels' => ['regular' => 'Regular', 'irregular' => 'Irregular', 'unknown' => 'Unknown'],
    'community_connections' => ['connected' => 'Connected', 'partially' => 'Partially', 'not_connected' => 'Not connected', 'unknown' => 'Unknown'],
    'priorities' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'],
    'triggers' => ['shahadah' => 'After shahadah', 'guidance_completed' => 'After guidance', 'returned_home' => 'After returning home', 'transfer_completed' => 'After transfer'],
    'schedule_statuses' => ['scheduled' => 'Scheduled', 'completed' => 'Done', 'missed' => 'Missed', 'cancelled' => 'Cancelled', 'rescheduled' => 'Rescheduled'],
    'contacted_persons' => ['convert' => 'Convert', 'family' => 'Family', 'employer' => 'Employer', 'friend' => 'Friend', 'local_dai' => "Local da'i", 'other' => 'Other'],
    'contact_results' => ['reached' => 'Reached', 'not_reached' => 'Not reached', 'wrong_number' => 'Wrong number', 'refused' => 'Refused'],
    'contact_methods' => ['phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'visit' => 'Visit', 'video_call' => 'Video call', 'email' => 'Email', 'other' => 'Other'],
    'types' => ['long_term' => 'Long-term follow-up', 'check_in' => 'Check-in'],
    'quiet_reason' => 'No long-term contact for :days days.',
    'fields' => [
        'status' => 'Status', 'last_contact_at' => 'Last contact', 'last_contact_method' => 'Contact method', 'last_known_country' => 'Last known country',
        'last_known_city' => 'Last known city', 'islamic_activity_status' => 'Islamic practice', 'community_connection_status' => 'Community connection',
        'next_follow_up_at' => 'Next follow-up', 'priority' => 'Priority', 'notes' => 'Notes', 'recorded_at' => 'Recorded', 'recorded_by' => 'By',
        'follow_up_at' => 'Contacted on', 'contact_method' => 'Method', 'contacted_person_type' => 'Contacted', 'contact_result' => 'Result',
        'current_country' => 'Current country', 'current_city' => 'Current city', 'mosque_or_center_name' => 'Mosque or center', 'local_dai_name' => "Local da'i",
        'support_needed' => 'Support needed', 'next_action' => 'Next action', 'confidentiality_level' => 'Confidentiality', 'convert' => 'Convert',
        'scheduled_at' => 'Due', 'type' => 'Type', 'reason' => 'Reason', 'dai' => "Da'i", 'overdue' => 'Overdue only',
    ],
    'actions' => ['record_status' => 'Update follow-up status', 'record' => 'Record contact', 'miss' => 'Missed', 'reschedule' => 'Reschedule', 'cancel' => 'Cancel'],
    'notifications' => ['saved' => 'Saved.'],
    'errors' => [
        'not_a_dai' => "Your account is not linked to a da'i of this center.",
        'convert_closed' => 'The convert\'s record is closed.',
        'invalid_transition' => 'This follow-up is no longer scheduled.',
        'reason_required' => 'A reason is required.',
        'date_in_past' => 'Choose a future date.',
        'schedule_mismatch' => 'This follow-up belongs to another convert.',
    ],
];
