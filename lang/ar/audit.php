<?php

declare(strict_types=1);

return [
    'model' => 'سجل تدقيق',
    'plural' => 'سجل التدقيق',
    'navigation_group' => 'الأمان',
    'platform' => 'المنصة',
    'fields' => [
        'created_at' => 'الوقت',
        'action' => 'الإجراء',
        'actor' => 'المنفّذ',
        'actor_type' => 'نوع المنفّذ',
        'tenant' => 'المركز',
        'subject' => 'السجل المعني',
        'privileged_grant' => 'تصريح الوصول المميز',
        'ip_address' => 'عنوان IP',
        'user_agent' => 'المتصفح',
        'request_id' => 'معرّف الطلب',
        'old_values' => 'القيم السابقة',
        'new_values' => 'القيم الجديدة',
    ],
    'actor_types' => [
        'user' => 'مستخدم',
        'system' => 'النظام',
        'job' => 'مهمة خلفية',
    ],
    'filters' => [
        'from' => 'من',
        'until' => 'إلى',
    ],
];
