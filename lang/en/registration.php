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
        'witnesses' => 'Witnesses',
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
        'witnesses' => 'Witnesses',
        'witness_name' => 'Name',
        'witness_phone' => 'Phone',
        'witness_identity_last4' => 'Last 4 digits of ID',
        'witness_relationship' => 'Relationship to the convert',
    ],
    'help' => [
        'prospect' => "Leave empty for a walk-in; then choose the primary da'i.",
        'witnesses' => 'The people who witnessed the shahadah, with a phone number for each.',
    ],
    'actions' => [
        'register' => 'Register',
        'verify' => 'Verify',
        'register_shahadah' => 'Register shahadah',
        'add_witness' => 'Add witness',
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
        'witnesses_required' => '{1} Record at least one witness.|[2,*] Record at least :count witnesses.',
        'too_many_witnesses' => 'Record at most :count witnesses.',
        'invalid_witness' => 'Each witness needs a name and a valid phone number; the ID suffix is four digits.',
        'duplicate_witness' => 'The same witness is listed twice.',
    ],
];
