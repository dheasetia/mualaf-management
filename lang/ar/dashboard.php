<?php

declare(strict_types=1);

return [
    'title' => 'لوحة المتابعة',
    'actions' => [
        'register_shahadah' => 'تسجيل مسلم جديد',
        'new_prospect' => 'مدعو جديد',
        'follow_ups' => 'جدول المتابعة',
    ],
    'stats' => [
        'converts_active' => 'المسلمون الجدد',
        'converts_active_help' => 'إجمالي المسلمين الجدد النشطين في المركز',
        'prospects_open' => 'المدعوون',
        'prospects_open_help' => 'مدعوون لم تُغلق متابعتهم بعد',
        'follow_ups_overdue' => 'متابعات متأخرة',
        'follow_ups_overdue_help' => 'متابعات فات موعدها ولم تُسجل',
        'shahadah_this_month' => 'إشهار الإسلام هذا الشهر',
        'shahadah_this_month_help' => 'حالات موثقة منذ بداية الشهر',
        'as_of' => 'حتى :date',
        'not_aggregated' => 'لم تُحسب الإحصاءات بعد',
    ],
    'my_follow_ups' => [
        'heading' => 'متابعاتي اليوم',
        'description' => 'المتأخرة والمقررة لليوم.',
        'empty' => 'لا توجد متابعات لليوم.',
        'time' => 'الموعد',
    ],
    'awaiting_verification' => [
        'heading' => 'بانتظار توثيقي',
        'description' => 'تسجيلات إشهار الإسلام التي يمكنك توثيقها (لا تشمل ما سجلته أو صححته بنفسك).',
        'empty' => 'لا توجد تسجيلات بانتظار التوثيق.',
        'registered_by' => 'سجّلها',
    ],
    'year_summary' => [
        'heading' => 'حصيلة عام :year',
        'last_30_days' => 'آخر 30 يومًا: :count',
    ],
    'charts' => [
        'shahadah_heading' => 'إشهار الإسلام شهريًا',
        'shahadah_description' => 'الحالات الموثقة في آخر 12 شهرًا',
        'activity_heading' => 'نشاط المركز',
        'activity_description' => 'آخر 6 أشهر',
    ],
    'operations' => [
        'heading' => 'سير العمل الآن',
        'dais_active' => 'الدعاة النشطون',
        'dais_active_help' => 'دعاة على رأس العمل',
        'classes_running' => 'فصول التأهيل الجارية',
        'classes_running_help' => 'مفتوحة أو قيد التنفيذ',
        'exams_upcoming' => 'اختبارات قادمة',
        'exams_upcoming_help' => 'خلال 30 يومًا',
        'transfers_open' => 'طلبات نقل جارية',
        'transfers_open_help' => 'من المركز أو إليه',
        'pickups_open' => 'طلبات توصيل مفتوحة',
        'pickups_open_help' => 'مطلوبة أو مُسندة',
        'pilgrims_open' => 'طلبات العمرة والحج',
        'pilgrims_open_help' => 'قيد المعالجة',
    ],
];
