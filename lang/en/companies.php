<?php

declare(strict_types=1);

return [
    'model' => 'company',
    'plural' => 'Companies',
    'fields' => [
        'name_ar' => 'Name (Arabic)', 'name_en' => 'Name (English)', 'registration_number' => 'Commercial registration',
        'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website', 'city' => 'City', 'address' => 'Address',
        'district' => 'District', 'postal_code' => 'Postal code', 'latitude' => 'Latitude', 'longitude' => 'Longitude',
        'google_maps_url' => 'Google Maps link', 'notes' => 'Notes', 'owner' => 'Directory', 'map' => 'Map',
    ],
    'help' => [
        'google_maps_url' => 'Paste a Google Maps link; coordinates are read from it when the latitude and longitude fields are empty.',
    ],
    'owner' => [
        'national' => 'National', 'office' => 'This center',
    ],
    'actions' => [
        'promote' => 'Move to national directory', 'open_map' => 'Open in Google Maps',
    ],
    'notifications' => [
        'promoted' => 'The company is now in the national directory.',
    ],
    'errors' => [
        'not_owned' => 'Only the owner of this company can change it.',
        'invalid_maps_url' => 'Use an https link from Google Maps.',
        'already_national' => 'This company is already in the national directory.',
    ],
];
