<?php

declare(strict_types=1);

return [
    'model' => "da'i",
    'plural' => "Da'i",
    'navigation_group' => 'Dawah',
    'sections' => [
        'profile' => 'Profile',
        'contact' => 'Contact and account',
        'employment' => 'Employment and supervision',
        'languages' => 'Languages',
    ],
    'fields' => [
        'employee_number' => 'Employee number',
        'full_name' => 'Full name',
        'full_name_ar' => 'Full name (Arabic)',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'education' => 'Education',
        'specialties' => 'Specialties',
        'phone' => 'Phone',
        'email' => 'Email',
        'user' => 'User account',
        'employment_status' => 'Employment',
        'joined_at' => 'Joined',
        'supervisor' => 'Supervisor',
        'languages' => 'Languages',
        'language' => 'Language',
        'proficiency' => 'Proficiency',
        'status' => 'Status',
        'reason' => 'Reason',
    ],
    'help' => [
        'user' => "Link when the da'i signs in to the system. Only members of this center are listed.",
    ],
    'actions' => [
        'add_language' => 'Add language',
        'change_status' => 'Change status',
    ],
    'notifications' => [
        'status_changed' => 'Status changed.',
    ],
    'statuses' => [
        'active' => 'Active',
        'on_leave' => 'On leave',
        'inactive' => 'Inactive',
    ],
    'employment_statuses' => [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'volunteer' => 'Volunteer',
        'contract' => 'Contract',
    ],
    'proficiencies' => [
        'native' => 'Native',
        'fluent' => 'Fluent',
        'working' => 'Working',
        'basic' => 'Basic',
    ],
    'workload' => [
        'title' => "Da'i workload",
        'primary_prospects' => 'Prospects (primary)',
        'primary_converts' => 'Converts (primary)',
        'other_assignments' => 'Other assignments',
        'supervisees' => "Supervised da'i",
    ],
    'follow_up_types' => [
        'visit' => 'Visit', 'call' => 'Call', 'message' => 'Message', 'discussion' => 'Discussion',
        'dialogue' => 'Dialogue', 'class' => 'Class', 'meeting' => 'Meeting', 'other' => 'Other',
    ],
    'contact_methods' => [
        'in_person' => 'In person', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'video' => 'Video call',
        'email' => 'Email', 'other' => 'Other',
    ],
    'confidentiality' => [
        'normal' => 'Normal', 'confidential' => 'Confidential',
    ],
    'errors' => [
        'assignee_unavailable' => "The da'i must be an active da'i of the office responsible for this person.",
        'not_a_dai' => "Only users with a da'i record in this center can record follow-ups.",
        'duplicate_employee_number' => "Another da'i in this center already has this employee number.",
        'user_already_linked' => "This user account is already linked to another da'i in this center.",
        'user_not_member' => 'The user account must be an active member of this center.',
        'supervisor_self' => "A da'i cannot supervise themselves.",
        'supervisor_cycle' => "This supervisor is already supervised, directly or indirectly, by this da'i.",
        'supervisor_unavailable' => "The supervisor must be an active da'i of this center.",
        'invalid_transition' => 'Status cannot change from :from to :to.',
        'has_active_primary' => "This da'i is still the primary da'i for :count people. Reassign them first.",
    ],
];
