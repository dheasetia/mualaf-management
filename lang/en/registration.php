<?php

declare(strict_types=1);

return [
    'model' => 'registration',
    'plural' => 'Registrations',
    'page_title' => 'Register shahadah',
    'sections' => [
        'source' => 'Prospect or walk-in',
        'identity' => 'Identity document',
        'profile' => 'Convert',
        'shahadah' => 'Shahadah',
        'contact' => 'Contact',
    ],
    'fields' => [
        'prospect' => 'Prospect (ready for shahadah)',
        'primary_dai' => "Primary da'i",
        'shahadah_date' => 'Date of shahadah',
        'shahadah_time' => 'Time',
        'location' => 'Place',
        'city' => 'City',
        'preacher' => "Da'i present",
        'notes' => 'Notes',
        'registration_no' => 'Registration number',
        'registration_date' => 'Registered on',
        'convert' => 'Convert',
        'status' => 'Status',
        'registered_by' => 'Registered by',
    ],
    'help' => [
        'prospect' => "Leave empty for a walk-in; then choose the primary da'i.",
    ],
    'actions' => [
        'register' => 'Register',
        'verify' => 'Verify',
        'register_shahadah' => 'Register shahadah',
    ],
    'notifications' => [
        'registered' => 'Registered as :number. Waiting for verification.',
        'verified' => 'Shahadah verified.',
    ],
    'registration_statuses' => [
        'draft' => 'Draft', 'pending_verification' => 'Waiting for verification', 'verified' => 'Verified',
        'rejected' => 'Rejected', 'voided' => 'Voided',
    ],
    'shahadah_statuses' => [
        'recorded' => 'Recorded', 'verified' => 'Verified', 'voided' => 'Voided',
    ],
    'record_types' => [
        'first' => 'First shahadah', 'renewal_ceremony' => 'Renewal ceremony',
    ],
    'errors' => [
        'possible_duplicate' => 'This person may already be registered. A duplicate review case was opened for the Head of Dawah; register again after the review.',
        'primary_dai_required' => "Choose the primary da'i for a walk-in.",
        'date_in_future' => 'The date of shahadah cannot be in the future.',
        'same_user' => 'A registration is verified by someone other than the person who recorded it.',
        'not_pending' => 'This registration is not waiting for verification.',
    ],
];
