<?php

declare(strict_types=1);

return [
    'model' => 'duplicate review',
    'plural' => 'Duplicate reviews',
    'fields' => [
        'candidate_name' => 'Person being registered',
        'candidate_date_of_birth' => 'Their date of birth',
        'match_reason' => 'Match',
        'match_score' => 'Score',
        'status' => 'Status',
        'decision' => 'Decision',
        'notes' => 'Notes',
        'opened_by' => 'Opened by',
        'opened_at' => 'Opened',
        'possible_convert' => 'Registered convert',
        'office' => 'Office',
        'name' => 'Name',
        'date_of_birth' => 'Date of birth',
        'nationality' => 'Nationality',
        'details' => 'What matched',
    ],
    'actions' => [
        'resolve' => 'Decide',
    ],
    'notifications' => [
        'resolved' => 'Decision saved.',
    ],
    'help' => [
        'same_person' => 'Same person: the registration stays blocked; request a transfer from the other office instead.',
        'different_person' => 'Different person: the registration can be submitted again and this pair will not be flagged again.',
    ],
    'candidate_types' => [
        'prospect' => 'Prospect', 'registration_draft' => 'Walk-in registration', 'import_row' => 'Import row',
    ],
    'match_reasons' => [
        'exact_identity' => 'Same identity', 'fuzzy_name_dob' => 'Similar name and details',
        'phone_match' => 'Same phone number', 'manual_report' => 'Reported',
    ],
    'review_statuses' => [
        'open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved',
    ],
    'decisions' => [
        'same_person' => 'Same person', 'different_person' => 'Different person', 'inconclusive' => 'Inconclusive',
    ],
    'errors' => [
        'notes_required' => 'Explain the decision.',
        'resolved' => 'This case is already resolved.',
    ],
];
