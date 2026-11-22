<?php

declare(strict_types=1);

return [
    'identity_types' => [
        'national_id' => 'Saudi national ID',
        'iqamah' => 'Iqamah',
        'passport' => 'Passport',
        'gcc_id' => 'GCC ID',
        'border_number' => 'Border number',
        'other' => 'Other',
    ],
    'model' => 'convert',
    'plural' => 'Converts',
    'alias' => 'Convert :code',
    'statuses' => [
        'active' => 'Active', 'inactive' => 'Inactive', 'deceased' => 'Deceased', 'record_closed' => 'Record closed',
    ],
    'disclosure_levels' => [
        'normal' => 'Normal', 'restricted' => 'Restricted', 'high_confidentiality' => 'High confidentiality',
    ],
    'contact_types' => [
        'mobile' => 'Mobile', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'telegram' => 'Telegram', 'other' => 'Other',
    ],
    'sections' => [
        'profile' => 'Profile', 'sensitive' => 'Religious background', 'identifiers' => 'Identity documents',
        'contacts' => 'Contacts', 'history' => 'Record status history',
    ],
    'fields' => [
        'full_name' => 'Full name', 'name_ar' => 'Name (Arabic)', 'previous_name' => 'Previous name',
        'islamic_name' => 'Islamic name', 'display_name' => 'Name', 'gender' => 'Gender', 'date_of_birth' => 'Date of birth',
        'nationality' => 'Nationality', 'native_language' => 'Native language', 'previous_religion' => 'Previous religion',
        'previous_religion_other' => 'Previous religion (other)', 'status' => 'Record status',
        'disclosure_risk_level' => 'Disclosure level', 'primary_dai' => "Primary da'i", 'origin_office' => 'Office of origin',
        'reason' => 'Reason', 'contact_type' => 'Type', 'value' => 'Contact', 'is_primary' => 'Primary',
        'identity_type' => 'Type', 'identity_number' => 'Number', 'issuing_country' => 'Issued by',
        'verification_status' => 'Verification', 'changed_by' => 'Changed by', 'changed_at' => 'Changed at',
    ],
    'actions' => [
        'change_disclosure' => 'Change disclosure level', 'change_status' => 'Change record status',
        'assign_dai' => "Assign da'i", 'add_contact' => 'Add contact', 'remove_contact' => 'Remove', 'reveal' => 'Show number',
    ],
    'notifications' => [
        'saved' => 'Saved.', 'revealed' => 'Number: :number (this view was logged)',
    ],
    'job_statuses' => ['employed' => 'Employed', 'ended' => 'Ended', 'unknown' => 'Unknown'],
    'residence_types' => [
        'company_dormitory' => 'Company dormitory', 'workers_camp' => 'Workers camp', 'house' => 'House',
        'apartment' => 'Apartment', 'other' => 'Other',
    ],
    'contact_roles' => [
        'dormitory_head' => 'Dormitory head', 'hr' => 'HR', 'public_relations' => 'Public relations',
        'supervisor' => 'Supervisor', 'friend' => 'Friend', 'family' => 'Family', 'other' => 'Other',
    ],
    'disclosure_scopes' => [
        'none' => 'Knows nothing', 'general_support' => 'General support only', 'knows_conversion' => 'Knows about the conversion',
    ],
    'locations' => [
        'employments' => 'Employment', 'residences' => 'Residence', 'contact_persons' => 'Contact persons',
        'company' => 'Company', 'company_name_free' => 'Company (not in directory)', 'occupation' => 'Occupation',
        'department' => 'Department', 'employee_number' => 'Employee number', 'work_location' => 'Work location',
        'work_city' => 'Work city', 'work_phone' => 'Work phone', 'employment_status' => 'Status',
        'started_at' => 'From', 'ended_at' => 'Until', 'is_current' => 'Current',
        'residence_type' => 'Type', 'name' => 'Name', 'city' => 'City', 'address' => 'Address', 'district' => 'District',
        'building_no' => 'Building', 'room_no' => 'Room', 'is_pickup_point' => 'Pickup point',
        'pickup_instructions' => 'Pickup instructions', 'latitude' => 'Latitude', 'longitude' => 'Longitude',
        'google_maps_url' => 'Google Maps link', 'notes' => 'Notes', 'position' => 'Position', 'contact_role' => 'Role',
        'phone' => 'Phone', 'alternate_phone' => 'Alternate phone', 'email' => 'Email', 'preferred_language' => 'Language',
        'permission_to_contact' => 'May be contacted', 'disclosure_scope' => 'What they know', 'is_primary' => 'Primary',
    ],
    'search' => [
        'title' => 'National identity search',
        'description' => 'Checks whether a person is already registered as a convert in any center. Searches are logged and limited to :max per hour.',
        'identity_type' => 'Identity type',
        'issuing_country' => 'Issuing country',
        'identity_number' => 'Identity number',
        'submit' => 'Search',
        'not_found' => 'Not registered. No convert record has this identity.',
        'found_here' => 'Already registered in this center.',
        'found_elsewhere' => 'Already registered in :office.',
        'withheld' => 'Registered in another center. Contact the central administration.',
        'name' => 'Name: :name',
    ],
    'errors' => [
        'invalid_maps_url' => 'Use an https link from Google Maps.',
        'reason_required' => 'A reason is required.',
        'invalid_transition' => 'Record status cannot change from :from to :to.',
        'closed' => 'This record is closed and can no longer be changed.',
        'invalid_contact' => 'This contact is not valid for the selected type.',
        'invalid_identity_number' => 'This identity number is not valid for the selected type.',
        'issuing_country_required' => 'Choose the issuing country.',
        'search_limit' => 'Search limit reached. Try again in :minutes minutes.',
        'already_registered' => 'This identity is already registered to a convert.',
    ],
];
