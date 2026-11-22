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
        'invalid_identity_number' => 'This identity number is not valid for the selected type.',
        'issuing_country_required' => 'Choose the issuing country.',
        'search_limit' => 'Search limit reached. Try again in :minutes minutes.',
        'already_registered' => 'This identity is already registered to a convert.',
    ],
];
