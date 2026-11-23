<?php

declare(strict_types=1);

return [
    'navigation_group' => 'الشؤون الإدارية',
    'model' => 'طلب إداري',
    'plural' => 'الطلبات الإدارية',
    'documents' => ['plural' => 'المستندات'],
    'statuses' => [
        'draft' => 'مسودة', 'submitted' => 'مقدم', 'under_verification' => 'قيد التحقق', 'needs_correction' => 'يحتاج تصحيحًا',
        'approved' => 'معتمد', 'processing' => 'قيد المعالجة', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغى',
    ],
    'document_checks' => ['pending' => 'لم يُفحص', 'verified' => 'تم التحقق', 'rejected' => 'مرفوض'],
    'decisions' => ['approved' => 'اعتماد', 'rejected' => 'رفض', 'returned' => 'إعادة'],
    'assignment_types' => ['verifier' => 'المدقق', 'processor' => 'المعالج', 'approver' => 'المعتمد'],
    'types' => ['model' => 'نوع طلب', 'plural' => 'أنواع الطلبات الإدارية'],
    'fields' => [
        'code' => 'الرمز', 'name_ar' => 'الاسم بالعربية', 'name_en' => 'الاسم بالإنجليزية', 'description' => 'الوصف',
        'required_documents' => 'المستندات', 'required' => 'إلزامي', 'approval_stages' => 'مراحل الاعتماد', 'sla_days' => 'أيام الإنجاز',
        'letter_type' => 'الخطاب الصادر عند الإكمال', 'active' => 'مفعّل',
        'reference_no' => 'الرقم المرجعي', 'convert' => 'المهتدي', 'type' => 'النوع', 'status' => 'الحالة', 'notes' => 'ملاحظات',
        'submitted_at' => 'تاريخ التقديم', 'due_at' => 'الموعد المستهدف', 'assigned_to' => 'المكلف', 'correction_note' => 'التصحيح المطلوب',
        'requirement' => 'المستند المطلوب', 'file' => 'الملف', 'check' => 'الفحص', 'note' => 'ملاحظة', 'reason' => 'السبب',
        'addressee' => 'الجهة الموجه إليها الخطاب', 'user' => 'المستخدم', 'assignment_type' => 'الدور', 'events' => 'السجل',
        'event' => 'الحدث', 'when' => 'الوقت', 'approvals' => 'الاعتمادات', 'stage' => 'المرحلة', 'decision' => 'القرار', 'letter' => 'الخطاب الصادر',
    ],
    'events' => [
        'created' => 'إنشاء', 'document_attached' => 'إرفاق مستند', 'document_verified' => 'التحقق من مستند',
        'document_rejected' => 'رفض مستند', 'status_changed' => 'تغيير الحالة', 'stage_approved' => 'اعتماد مرحلة', 'assigned' => 'تكليف',
    ],
    'actions' => [
        'create' => 'طلب جديد', 'attach' => 'إرفاق مستند', 'submit' => 'تقديم', 'start_verification' => 'بدء التحقق',
        'verify_document' => 'تم التحقق', 'reject_document' => 'رفض المستند', 'request_correction' => 'طلب تصحيح',
        'approve' => 'اعتماد', 'reject' => 'رفض', 'start_processing' => 'بدء المعالجة', 'complete' => 'إكمال وإصدار الخطاب',
        'cancel' => 'إلغاء', 'assign' => 'تكليف', 'download' => 'تنزيل',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'invalid_type_code' => 'استخدم حروفًا لاتينية صغيرة وأرقامًا وشرطة سفلية، تبدأ بحرف.',
        'type_code_locked' => 'لا يمكن تغيير الرمز بعد استخدام هذا النوع في طلبات.',
        'type_code_taken' => 'هذا الرمز مستخدم لنوع آخر.',
        'invalid_requirements' => 'لكل مستند رمز فريد من حروف لاتينية صغيرة وأرقام وشرطة سفلية.',
        'invalid_letter' => 'اختر خطابًا يصدر للمهتدين.',
        'invalid_transition' => 'لا يمكن نقل الطلب من :from إلى :to.',
        'reason_required' => 'السبب مطلوب.',
        'convert_unavailable' => 'اختر مهتديًا متاحًا لك في هذا المركز.',
        'type_unavailable' => 'اختر نوع طلب مفعّلًا.',
        'unknown_requirement' => 'هذا المستند ليس من متطلبات نوع الطلب.',
        'documents_locked' => 'لا تُغيَّر المستندات إلا والطلب مسودة أو معاد للتصحيح.',
        'missing_documents' => 'أرفق المستندات المطلوبة أولًا: :documents.',
        'documents_not_verified' => 'يجب التحقق من كل المستندات المرفقة أولًا.',
        'approver_repeated' => 'كل مرحلة اعتماد تحتاج شخصًا مختلفًا غير منشئ الطلب.',
        'assignee_lacks_permission' => 'هذا المستخدم لا يملك صلاحية هذا الدور هنا.',
    ],
];
