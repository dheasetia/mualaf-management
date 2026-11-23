<?php

declare(strict_types=1);

return [
    'navigation_group' => 'العمرة والحج',
    'programs' => ['model' => 'برنامج', 'plural' => 'برامج العمرة والحج'],
    'registrations' => ['model' => 'تسجيل', 'plural' => 'التسجيلات'],
    'requirements' => ['model' => 'متطلب', 'plural' => 'المتطلبات'],
    'groups' => ['model' => 'مجموعة', 'plural' => 'المجموعات'],
    'program_types' => ['umrah' => 'عمرة', 'hajj' => 'حج'],
    'program_statuses' => ['draft' => 'مسودة', 'open' => 'التسجيل مفتوح', 'closed' => 'التسجيل مغلق', 'in_progress' => 'قيد التنفيذ', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'],
    'registration_statuses' => [
        'applied' => 'مقدم', 'eligibility_checked' => 'مستندات ناقصة', 'eligible' => 'مؤهل', 'ineligible' => 'غير مؤهل',
        'approved' => 'معتمد', 'waitlisted' => 'قائمة الانتظار', 'confirmed' => 'مؤكد', 'departed' => 'غادر', 'completed' => 'أتم', 'withdrawn' => 'منسحب',
    ],
    'requirement_statuses' => ['pending' => 'قيد الانتظار', 'fulfilled' => 'مستوفى', 'waived' => 'معفى'],
    'eligibility' => [
        'record_closed' => 'سجل المهتدي مغلق.',
        'shahadah_not_verified' => 'لم يتم التحقق من إشهار الإسلام.',
        'too_recent' => 'يجب أن يمضي على إشهار الإسلام :months شهرًا على الأقل عند بدء البرنامج.',
        'guidance_not_completed' => 'لم يُتم برنامج تأهيل.',
        'documents_pending' => 'ما زال :count مستند مطلوب قيد الانتظار.',
    ],
    'fields' => [
        'program_type' => 'النوع', 'name' => 'الاسم', 'season_year' => 'الموسم', 'starts_on' => 'البداية', 'ends_on' => 'النهاية',
        'registration_deadline' => 'آخر موعد للتسجيل', 'capacity' => 'المقاعد', 'seats' => 'المقاعد المشغولة', 'status' => 'الحالة',
        'min_months_since_shahadah' => 'أقل عدد أشهر منذ إشهار الإسلام', 'require_completed_guidance' => 'يشترط إتمام برنامج تأهيل',
        'convert' => 'المهتدي', 'eligibility_notes' => 'ملاحظات الأهلية', 'code' => 'الرمز', 'description' => 'الوصف', 'required' => 'إلزامي',
        'file' => 'الملف', 'note' => 'ملاحظة', 'reason' => 'السبب', 'group_name' => 'المجموعة', 'leader' => 'المشرف', 'members' => 'الأعضاء', 'group' => 'المجموعة',
    ],
    'actions' => [
        'register' => 'تسجيل مهتدٍ', 'check' => 'فحص الأهلية', 'approve' => 'اعتماد', 'confirm' => 'تأكيد', 'withdraw' => 'انسحاب',
        'open' => 'فتح', 'change_status' => 'تغيير الحالة', 'add_requirement' => 'إضافة متطلب', 'attach' => 'إرفاق مستند',
        'fulfil' => 'استيفاء', 'waive' => 'إعفاء', 'download' => 'تنزيل', 'add_group' => 'مجموعة جديدة', 'add_member' => 'إضافة حاج',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'invalid_transition' => 'لا يمكن الانتقال من :from إلى :to.',
        'reason_required' => 'السبب مطلوب.',
        'not_editable' => 'لا يمكن التعديل الآن.',
        'dates_invalid' => 'يجب أن ينتهي البرنامج بعد بدايته وأن يُغلق التسجيل قبل بدايته.',
        'capacity_below_seats' => 'لا يمكن أن تقل المقاعد عن المقاعد المشغولة.',
        'registration_closed' => 'التسجيل في هذا البرنامج مغلق.',
        'convert_unavailable' => 'اختر مهتديًا نشطًا متاحًا لك في هذا المركز.',
        'already_registered' => 'هذا المهتدي مسجل في البرنامج.',
        'program_full' => 'لا توجد مقاعد شاغرة في البرنامج.',
        'self_approval' => 'يجب أن يعتمد شخص غير من قام بالتسجيل.',
        'group_full' => 'المجموعة ممتلئة.',
        'not_in_program' => 'اختر حاجًا معتمدًا أو مؤكدًا في هذا البرنامج.',
        'duplicate_requirement' => 'رمز المتطلب موجود.',
        'leader_unavailable' => 'يجب أن يكون المشرف موظفًا يرى برامج العمرة والحج.',
    ],
];
