<?php

declare(strict_types=1);

return [
    'model' => 'مراجعة تكرار',
    'plural' => 'مراجعات التكرار',
    'fields' => [
        'candidate_name' => 'الشخص قيد التسجيل',
        'candidate_date_of_birth' => 'تاريخ ميلاده',
        'match_reason' => 'سبب التطابق',
        'match_score' => 'الدرجة',
        'status' => 'الحالة',
        'decision' => 'القرار',
        'notes' => 'ملاحظات',
        'opened_by' => 'فتحها',
        'opened_at' => 'تاريخ الفتح',
        'possible_convert' => 'المهتدي المسجل',
        'office' => 'المركز',
        'name' => 'الاسم',
        'date_of_birth' => 'تاريخ الميلاد',
        'nationality' => 'الجنسية',
        'details' => 'ما تطابق',
    ],
    'actions' => [
        'resolve' => 'اتخاذ قرار',
    ],
    'notifications' => [
        'resolved' => 'تم حفظ القرار.',
    ],
    'help' => [
        'same_person' => 'الشخص نفسه: يبقى التسجيل موقوفًا؛ اطلب النقل من المركز الآخر بدلًا من ذلك.',
        'different_person' => 'شخص مختلف: يمكن إعادة التسجيل ولن يُنبَّه على هذا الزوج مرة أخرى.',
    ],
    'candidate_types' => [
        'prospect' => 'مدعو', 'registration_draft' => 'تسجيل حضور مباشر', 'import_row' => 'سطر استيراد',
    ],
    'match_reasons' => [
        'exact_identity' => 'الهوية نفسها', 'fuzzy_name_dob' => 'تشابه الاسم والبيانات',
        'phone_match' => 'رقم الجوال نفسه', 'manual_report' => 'بلاغ',
    ],
    'review_statuses' => [
        'open' => 'مفتوحة', 'in_review' => 'قيد المراجعة', 'resolved' => 'محسومة',
    ],
    'decisions' => [
        'same_person' => 'الشخص نفسه', 'different_person' => 'شخص مختلف', 'inconclusive' => 'غير محسوم',
    ],
    'errors' => [
        'notes_required' => 'اشرح سبب القرار.',
        'resolved' => 'هذه الحالة محسومة مسبقًا.',
    ],
];
