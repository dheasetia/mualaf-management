<?php

declare(strict_types=1);

return [
    'model' => 'وصول استثنائي', 'plural' => 'الوصول الاستثنائي',
    'help' => 'وصول إلى بيانات مهتدٍ واحد لسبب محدد، بموافقة شخص آخر، ولمدة محدودة. يُسجل كل اطلاع في سجل التدقيق.',
    'statuses' => ['pending' => 'بانتظار الموافقة', 'rejected' => 'مرفوض', 'active' => 'نشط', 'expired' => 'منتهي', 'revoked' => 'مُلغى'],
    'scopes' => ['profile' => 'الملف', 'contacts' => 'جهات الاتصال', 'identity' => 'أرقام الهوية', 'shahadah' => 'إشهار الإسلام', 'follow_ups' => 'المتابعة'],
    'fields' => [
        'office' => 'المركز', 'identity_type' => 'نوع الهوية', 'identity_number' => 'رقم الهوية', 'reason' => 'السبب', 'scope' => 'النطاق',
        'minutes' => 'المدة (دقائق)', 'status' => 'الحالة', 'requested_by' => 'مقدم الطلب', 'expires_at' => 'ينتهي', 'note' => 'ملاحظة', 'requested_at' => 'تاريخ الطلب',
    ],
    'actions' => ['request' => 'طلب وصول', 'approve' => 'موافقة', 'reject' => 'رفض', 'revoke' => 'إنهاء الوصول', 'open' => 'فتح'],
    'view_fields' => [
        'full_name' => 'الاسم الكامل', 'islamic_name' => 'الاسم الإسلامي', 'gender' => 'الجنس', 'nationality' => 'الجنسية', 'status' => 'الحالة',
        'office' => 'المركز', 'disclosure' => 'مستوى الإفصاح', 'primary_dai' => 'الداعية الأساسي', 'date' => 'التاريخ', 'location' => 'المكان',
        'witnesses' => 'الشهود', 'last_contact_at' => 'آخر تواصل', 'contacts_recorded' => 'مرات التواصل المسجلة',
    ],
    'no_permission_to_contact' => 'لم يوافق على التواصل',
    'view_notice' => 'وصول استثنائي — هذا الاطلاع مسجل في سجل التدقيق.',
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'reason_required' => 'السبب مطلوب.',
        'scope_required' => 'اختر ما تحتاج إلى الاطلاع عليه.',
        'duration_invalid' => 'اختر مدة من 15 دقيقة إلى 24 ساعة.',
        'convert_not_found' => 'لا يوجد مهتدٍ مسجل بهذه الهوية.',
        'self_approval' => 'يجب أن يقرر شخص غير مقدم الطلب.',
        'not_pending' => 'تم البت في هذا الطلب.',
        'not_active' => 'هذا الوصول غير نشط.',
        'platform_only' => 'يُدار الوصول الاستثنائي من المنصة المركزية.',
    ],
];
