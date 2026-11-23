<?php

declare(strict_types=1);

return [
    'navigation_group' => 'التقارير',
    'office_dashboard' => 'لوحة المركز',
    'national_dashboard' => 'اللوحة الوطنية',
    'exports' => ['model' => 'تصدير', 'plural' => 'التصدير'],
    'metrics' => [
        'shahadah_verified' => 'حالات إشهار الإسلام (موثقة)', 'prospects_created' => 'مدعوون جدد', 'follow_ups_recorded' => 'متابعات مسجلة',
        'enrollments_completed' => 'إتمام التأهيل', 'certificates_issued' => 'شهادات صادرة', 'plans_completed' => 'أنشطة منفذة',
        'submissions_completed' => 'طلبات مكتملة', 'converts_active' => 'المهتدون النشطون', 'prospects_open' => 'مدعوون قيد المتابعة',
        'follow_ups_overdue' => 'متابعات متأخرة', 'converts_total' => 'المهتدون المسجلون وطنيًا',
    ],
    'periods' => ['last_30_days' => 'آخر 30 يومًا', 'this_year' => 'هذا العام', 'now' => 'الآن'],
    'labels' => [
        'monthly_conversions' => 'حالات إشهار الإسلام شهريًا', 'by_office' => 'حالات إشهار الإسلام حسب المركز هذا العام', 'by_nationality' => 'أكثر الجنسيات هذا العام',
        'month' => 'الشهر', 'office' => 'المركز', 'country' => 'البلد', 'count' => 'العدد',
        'last_aggregated' => 'الأرقام حتى :date (تُحدَّث ليلًا).', 'not_aggregated' => 'لا توجد أرقام بعد: تُجمع الإحصاءات ليلًا.',
    ],
    'export_types' => ['converts' => 'قائمة المهتدين', 'shahadah_statistics' => 'إحصاءات إشهار الإسلام الشهرية'],
    'export_statuses' => ['queued' => 'في الانتظار', 'processing' => 'قيد الإعداد', 'ready' => 'جاهز', 'failed' => 'فشل', 'expired' => 'منتهي'],
    'fields' => [
        'export_type' => 'النوع', 'reason' => 'السبب', 'from' => 'من', 'to' => 'إلى', 'status' => 'الحالة', 'rows' => 'الصفوف',
        'requested_at' => 'تاريخ الطلب', 'expires_at' => 'متاح حتى', 'downloads' => 'مرات التنزيل',
    ],
    'actions' => ['request' => 'طلب تصدير', 'download' => 'تنزيل'],
    'notifications' => [
        'requested' => 'تم طلب التصدير',
        'password' => 'احتفظ بكلمة المرور هذه؛ تظهر مرة واحدة فقط وهي لازمة لفتح الملف: :password',
    ],
    'errors' => [
        'reason_required' => 'اذكر سبب تصدير البيانات الشخصية.',
        'too_many_exports' => 'بلغت حد التصدير؛ حاول بعد :minutes دقيقة.',
        'office_only' => 'تُصدَّر البيانات الشخصية من المركز وليس من المنصة.',
        'not_downloadable' => 'هذا التصدير غير متاح للتنزيل.',
    ],
];
