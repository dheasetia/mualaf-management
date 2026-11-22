<?php

declare(strict_types=1);

return [
    'convert_model' => 'نقل مهتدٍ',
    'convert_plural' => 'نقل المهتدين',
    'prospect_model' => 'نقل مدعو',
    'prospect_plural' => 'نقل المدعوين',
    'fields' => [
        'direction' => 'الاتجاه',
        'convert' => 'المهتدي',
        'prospect' => 'المدعو',
        'from' => 'من',
        'to' => 'إلى',
        'reason' => 'السبب',
        'note' => 'ملاحظة',
        'status' => 'الحالة',
        'requested_at' => 'تاريخ الطلب',
        'requested_by' => 'مقدم الطلب',
        'new_primary_dai' => 'الداعية الأساسي الجديد',
        'destination' => 'المركز المستقبل',
        'transferred_at' => 'تاريخ النقل',
    ],
    'directions' => [
        'incoming' => 'إلينا',
        'outgoing' => 'منا',
    ],
    'actions' => [
        'request' => 'طلب نقل',
        'start_review' => 'بدء المراجعة',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'accept' => 'قبول وإسناد داعية',
        'cancel' => 'إلغاء',
        'transfer_prospect' => 'نقل إلى مركز آخر',
    ],
    'notifications' => [
        'saved' => 'تم الحفظ.',
        'requested' => 'تم طلب النقل وسيراجعه المركز الحالي.',
    ],
    'statuses' => [
        'pending' => 'قيد الانتظار', 'under_review' => 'قيد المراجعة', 'approved' => 'تمت الموافقة', 'accepted' => 'مقبول',
        'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغى', 'escalated' => 'مُصعَّد إلى الإدارة المركزية',
    ],
    'errors' => [
        'not_found' => 'لا يوجد مهتدٍ مسجل بهذه الهوية.',
        'same_office' => 'هذا المهتدي في مركزك بالفعل.',
        'already_active' => 'يوجد طلب نقل جارٍ لهذا الشخص.',
        'recently_rejected' => 'رفض المركز الحالي طلبك مؤخرًا. أعد الطلب بعد :days يومًا أو تواصل مع الإدارة المركزية.',
        'invalid_transition' => 'لا يمكن نقل الطلب من :from إلى :to.',
        'note_required' => 'السبب مطلوب.',
        'not_your_side' => 'هذه الخطوة من اختصاص المركز الآخر.',
        'office_changed' => 'لم يعد المهتدي في المركز الذي وافق على النقل.',
        'invalid_destination' => 'اختر مركزًا آخر نشطًا.',
        'closed_prospect' => 'هذا المدعو مغلق ولا يمكن نقله.',
    ],
];
