<?php

declare(strict_types=1);

return [
    'statuses' => ['plural' => 'حالة المتابعة'],
    'notes' => ['model' => 'متابعة طويلة المدى', 'plural' => 'المتابعة طويلة المدى'],
    'schedules' => ['model' => 'متابعة', 'plural' => 'جدول المتابعة'],
    'states' => [
        'active_guidance' => 'في برنامج التأهيل', 'stable' => 'مستقر', 'needs_follow_up' => 'يحتاج متابعة',
        'needs_community_connection' => 'يحتاج ربطًا بالمجتمع', 'temporarily_unreachable' => 'يتعذر التواصل مؤقتًا',
        'lost_contact' => 'انقطع التواصل', 'returned_home' => 'عاد إلى بلده', 'followed_abroad' => 'متابَع في الخارج',
    ],
    'activity_levels' => ['regular' => 'منتظم', 'irregular' => 'غير منتظم', 'unknown' => 'غير معروف'],
    'community_connections' => ['connected' => 'مرتبط', 'partially' => 'مرتبط جزئيًا', 'not_connected' => 'غير مرتبط', 'unknown' => 'غير معروف'],
    'priorities' => ['low' => 'منخفضة', 'normal' => 'عادية', 'high' => 'عالية'],
    'triggers' => ['shahadah' => 'بعد إشهار الإسلام', 'guidance_completed' => 'بعد التأهيل', 'returned_home' => 'بعد العودة إلى البلد', 'transfer_completed' => 'بعد النقل'],
    'schedule_statuses' => ['scheduled' => 'مجدولة', 'completed' => 'تمت', 'missed' => 'فاتت', 'cancelled' => 'ملغاة', 'rescheduled' => 'أعيدت جدولتها'],
    'contacted_persons' => ['convert' => 'المهتدي', 'family' => 'الأسرة', 'employer' => 'جهة العمل', 'friend' => 'صديق', 'local_dai' => 'داعية محلي', 'other' => 'آخر'],
    'contact_results' => ['reached' => 'تم التواصل', 'not_reached' => 'لم يتم التواصل', 'wrong_number' => 'رقم خاطئ', 'refused' => 'رفض'],
    'contact_methods' => ['phone' => 'هاتف', 'whatsapp' => 'واتساب', 'visit' => 'زيارة', 'video_call' => 'مكالمة فيديو', 'email' => 'بريد إلكتروني', 'other' => 'أخرى'],
    'types' => ['long_term' => 'متابعة طويلة المدى', 'check_in' => 'اطمئنان'],
    'quiet_reason' => 'لا يوجد تواصل طويل المدى منذ :days يومًا.',
    'fields' => [
        'status' => 'الحالة', 'last_contact_at' => 'آخر تواصل', 'last_contact_method' => 'وسيلة التواصل', 'last_known_country' => 'آخر بلد معروف',
        'last_known_city' => 'آخر مدينة معروفة', 'islamic_activity_status' => 'الالتزام', 'community_connection_status' => 'الارتباط بالمجتمع',
        'next_follow_up_at' => 'المتابعة القادمة', 'priority' => 'الأولوية', 'notes' => 'ملاحظات', 'recorded_at' => 'التاريخ', 'recorded_by' => 'بواسطة',
        'follow_up_at' => 'تاريخ التواصل', 'contact_method' => 'الوسيلة', 'contacted_person_type' => 'تم التواصل مع', 'contact_result' => 'النتيجة',
        'current_country' => 'البلد الحالي', 'current_city' => 'المدينة الحالية', 'mosque_or_center_name' => 'المسجد أو المركز', 'local_dai_name' => 'الداعية المحلي',
        'support_needed' => 'الدعم المطلوب', 'next_action' => 'الإجراء التالي', 'confidentiality_level' => 'السرية', 'convert' => 'المهتدي',
        'scheduled_at' => 'الموعد', 'type' => 'النوع', 'reason' => 'السبب', 'dai' => 'الداعية', 'overdue' => 'المتأخرة فقط',
    ],
    'actions' => ['record_status' => 'تحديث حالة المتابعة', 'record' => 'تسجيل تواصل', 'miss' => 'فاتت', 'reschedule' => 'إعادة جدولة', 'cancel' => 'إلغاء'],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'not_a_dai' => 'حسابك غير مرتبط بداعية في هذا المركز.',
        'convert_closed' => 'سجل المهتدي مغلق.',
        'invalid_transition' => 'هذه المتابعة لم تعد مجدولة.',
        'reason_required' => 'السبب مطلوب.',
        'date_in_past' => 'اختر تاريخًا قادمًا.',
        'schedule_mismatch' => 'هذه المتابعة تخص مهتديًا آخر.',
    ],
];
