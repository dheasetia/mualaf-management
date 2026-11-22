<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Master data',
    'fields' => [
        'code' => 'Code',
        'iso2' => 'ISO alpha-2',
        'iso3' => 'ISO alpha-3',
        'name_ar' => 'Name (Arabic)',
        'name_en' => 'Name (English)',
        'sort_order' => 'Order',
        'region' => 'Region',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'phone_code' => 'Calling code',
        'is_rtl' => 'Right to left',
        'active' => 'Active',
        'category' => 'Category',
        'requires_verification' => 'Requires verification',
    ],
    'regions' => ['model' => 'region', 'plural' => 'Regions', 'cities_count' => 'Cities'],
    'cities' => ['model' => 'city', 'plural' => 'Cities'],
    'countries' => ['model' => 'country', 'plural' => 'Countries'],
    'languages' => ['model' => 'language', 'plural' => 'Languages'],
    'religions' => ['model' => 'religion', 'plural' => 'Religions'],
    'budget_categories' => ['model' => 'budget category', 'plural' => 'Budget categories'],
    'dai_activity_types' => ['model' => "da'i activity type", 'plural' => "Da'i activity types"],
    'activity_categories' => [
        'dawah' => 'Dawah',
        'education' => 'Education',
        'scholarly_media' => 'Scholarly and media',
        'social' => 'Social',
    ],
    'genders' => [
        'male' => 'Male',
        'female' => 'Female',
    ],
];
