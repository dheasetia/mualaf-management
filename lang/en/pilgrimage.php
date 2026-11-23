<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Umrah and Hajj',
    'programs' => ['model' => 'program', 'plural' => 'Umrah and Hajj programs'],
    'registrations' => ['model' => 'registration', 'plural' => 'Registrations'],
    'requirements' => ['model' => 'requirement', 'plural' => 'Requirements'],
    'groups' => ['model' => 'group', 'plural' => 'Groups'],
    'program_types' => ['umrah' => 'Umrah', 'hajj' => 'Hajj'],
    'program_statuses' => ['draft' => 'Draft', 'open' => 'Open for registration', 'closed' => 'Registration closed', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'registration_statuses' => [
        'applied' => 'Applied', 'eligibility_checked' => 'Documents pending', 'eligible' => 'Eligible', 'ineligible' => 'Not eligible',
        'approved' => 'Approved', 'waitlisted' => 'Waitlisted', 'confirmed' => 'Confirmed', 'departed' => 'Departed', 'completed' => 'Completed', 'withdrawn' => 'Withdrawn',
    ],
    'requirement_statuses' => ['pending' => 'Pending', 'fulfilled' => 'Fulfilled', 'waived' => 'Waived'],
    'eligibility' => [
        'record_closed' => 'The convert\'s record is closed.',
        'shahadah_not_verified' => 'The shahadah is not verified.',
        'too_recent' => 'The shahadah must be at least :months months old when the program starts.',
        'guidance_not_completed' => 'No completed guidance program.',
        'documents_pending' => ':count required document(s) still pending.',
        'consent_missing' => 'No active consent to take part in a pilgrimage program has been recorded.',
    ],
    'fields' => [
        'activity' => 'Activity', 'transport_request' => 'Approved transport (optional)', 'pickup_at' => 'Pickup time',
        'program_type' => 'Type', 'name' => 'Name', 'season_year' => 'Season', 'starts_on' => 'Starts', 'ends_on' => 'Ends',
        'registration_deadline' => 'Registration deadline', 'capacity' => 'Seats', 'seats' => 'Seats taken', 'status' => 'Status',
        'min_months_since_shahadah' => 'Minimum months since shahadah', 'require_completed_guidance' => 'Requires a completed guidance program',
        'convert' => 'Convert', 'eligibility_notes' => 'Eligibility notes', 'code' => 'Code', 'description' => 'Description', 'required' => 'Required',
        'file' => 'File', 'note' => 'Note', 'reason' => 'Reason', 'group_name' => 'Group', 'leader' => 'Leader', 'members' => 'Members', 'group' => 'Group',
    ],
    'actions' => [
        'request_pickups' => 'Request pickups',
        'register' => 'Register a convert', 'check' => 'Check eligibility', 'approve' => 'Approve', 'confirm' => 'Confirm', 'withdraw' => 'Withdraw',
        'open' => 'Open', 'change_status' => 'Change status', 'add_requirement' => 'Add requirement', 'attach' => 'Attach document',
        'fulfil' => 'Mark fulfilled', 'waive' => 'Waive', 'download' => 'Download', 'add_group' => 'New group', 'add_member' => 'Add pilgrim',
    ],
    'notifications' => ['saved' => 'Saved.', 'pickups_requested' => ':created pickup(s) requested, :skipped skipped (already requested, no residence or no access).'],
    'help' => ['request_pickups' => 'One pickup per approved or confirmed pilgrim, from their pickup point or current residence, for an activity linked to this program.'],
    'errors' => [
        'invalid_transition' => 'Cannot move from :from to :to.',
        'reason_required' => 'A reason is required.',
        'not_editable' => 'This cannot be changed now.',
        'dates_invalid' => 'The program must end after it starts, and registration must close before it starts.',
        'capacity_below_seats' => 'The capacity cannot be lower than the seats already taken.',
        'registration_closed' => 'Registration for this program is closed.',
        'convert_unavailable' => 'Choose an active convert you can see in this center.',
        'already_registered' => 'This convert is already registered for the program.',
        'program_full' => 'The program has no free seat.',
        'self_approval' => 'Someone other than the person who registered must approve.',
        'group_full' => 'The group is full.',
        'not_in_program' => 'Choose an approved or confirmed pilgrim of this program.',
        'duplicate_requirement' => 'This requirement code already exists.',
        'leader_unavailable' => 'The leader must be a staff member who can see pilgrimage programs.',
    ],
];
