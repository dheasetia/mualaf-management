<?php

declare(strict_types=1);

return [
    'navigation_group' => 'البيانات الأساسية',
    'fields' => [
        'code' => 'الرمز',
        'iso2' => 'رمز ISO الثنائي',
        'iso3' => 'رمز ISO الثلاثي',
        'name_ar' => 'الاسم (عربي)',
        'name_en' => 'الاسم (إنجليزي)',
        'sort_order' => 'الترتيب',
        'region' => 'المنطقة',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'phone_code' => 'مفتاح الاتصال',
        'is_rtl' => 'من اليمين إلى اليسار',
        'active' => 'مفعّل',
        'category' => 'الفئة',
        'requires_verification' => 'يتطلب التحقق',
    ],
    'regions' => ['model' => 'منطقة', 'plural' => 'المناطق', 'cities_count' => 'المدن'],
    'cities' => ['model' => 'مدينة', 'plural' => 'المدن'],
    'countries' => ['model' => 'دولة', 'plural' => 'الدول'],
    'languages' => ['model' => 'لغة', 'plural' => 'اللغات'],
    'religions' => ['model' => 'ديانة', 'plural' => 'الديانات'],
    'budget_categories' => ['model' => 'بند ميزانية', 'plural' => 'بنود الميزانية'],
    'dai_activity_types' => ['model' => 'نوع نشاط الداعية', 'plural' => 'أنواع أنشطة الدعاة'],
    'activity_categories' => [
        'dawah' => 'الدعوة',
        'education' => 'التعليم',
        'scholarly_media' => 'العلمي والإعلامي',
        'social' => 'الاجتماعي',
    ],
];
