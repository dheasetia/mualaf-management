<?php

declare(strict_types=1);

return [
    'model' => 'شركة',
    'plural' => 'الشركات',
    'fields' => [
        'name_ar' => 'الاسم (عربي)', 'name_en' => 'الاسم (إنجليزي)', 'registration_number' => 'السجل التجاري',
        'phone' => 'الهاتف', 'email' => 'البريد الإلكتروني', 'website' => 'الموقع', 'city' => 'المدينة', 'address' => 'العنوان',
        'district' => 'الحي', 'postal_code' => 'الرمز البريدي', 'latitude' => 'خط العرض', 'longitude' => 'خط الطول',
        'google_maps_url' => 'رابط خرائط Google', 'notes' => 'ملاحظات', 'owner' => 'الدليل', 'map' => 'الخريطة',
    ],
    'help' => [
        'google_maps_url' => 'الصق رابطًا من خرائط Google؛ تُقرأ الإحداثيات منه إذا كان حقلا خط العرض والطول فارغين.',
    ],
    'owner' => [
        'national' => 'وطني', 'office' => 'هذا المركز',
    ],
    'actions' => [
        'promote' => 'نقل إلى الدليل الوطني', 'open_map' => 'فتح في خرائط Google',
    ],
    'notifications' => [
        'promoted' => 'أصبحت الشركة في الدليل الوطني.',
    ],
    'errors' => [
        'not_owned' => 'لا يغيّر هذه الشركة إلا مالكها.',
        'invalid_maps_url' => 'استخدم رابط https من خرائط Google.',
        'already_national' => 'هذه الشركة في الدليل الوطني بالفعل.',
    ],
];
