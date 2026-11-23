<?php

declare(strict_types=1);

return [
    'model' => 'شهادة',
    'plural' => 'الشهادات',
    'templates' => ['model' => 'قالب شهادة', 'plural' => 'قوالب الشهادات'],
    'types' => ['shahadah' => 'شهادة إشهار الإسلام', 'guidance_completion' => 'شهادة إتمام برنامج التأهيل'],
    'statuses' => [
        'draft' => 'مسودة', 'pending_approval' => 'بانتظار الاعتماد', 'approved' => 'معتمدة', 'issued' => 'صادرة',
        'rejected' => 'مرفوضة', 'revoked' => 'ملغاة', 'replaced' => 'مستبدلة',
    ],
    'fields' => [
        'certificate_number' => 'رقم الشهادة', 'type' => 'النوع', 'name' => 'الاسم', 'issued_on' => 'تاريخ الإصدار',
        'office' => 'المركز المصدر', 'status' => 'الحالة', 'convert' => 'المهتدي', 'enrollment' => 'التسجيل المكتمل',
        'reason' => 'السبب', 'pdf' => 'ملف PDF', 'template_code' => 'الرمز', 'version' => 'الإصدار', 'html_template' => 'قالب HTML',
        'orientation' => 'الاتجاه', 'active' => 'مفعّل', 'approved' => 'معتمد',
    ],
    'help' => [
        'html_template' => 'استخدم حقول اللقطة بالصيغة {{ field }}، مثل {{ full_name }} و{{ certificate_number }} و{{ issued_on_hijri_ar }}، و{{ qr_code }} لرمز التحقق. لا يُنفَّذ أي شيء آخر في القالب.',
    ],
    'pdf' => ['ready' => 'جاهز', 'processing' => 'قيد الإنشاء'],
    'actions' => [
        'request' => 'طلب شهادة', 'approve' => 'اعتماد', 'reject' => 'رفض', 'issue' => 'إصدار', 'revoke' => 'إلغاء الشهادة',
        'regenerate' => 'إعادة إنشاء PDF', 'download' => 'تنزيل PDF', 'new_version' => 'إصدار جديد', 'approve_template' => 'اعتماد وتفعيل',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'verify' => [
        'title' => 'التحقق من الشهادة', 'valid' => 'شهادة صحيحة', 'not_found' => 'تعذر التحقق من هذه الشهادة.',
    ],
    'errors' => [
        'shahadah_not_verified' => 'يجب التحقق من الشهادة (النطق بالشهادتين) أولًا.',
        'enrollment_not_completed' => 'اختر تسجيلًا مكتملًا لهذا المهتدي.',
        'attendance_too_low' => 'نسبة الحضور أقل من الحد الأدنى للبرنامج.',
        'final_exam_not_passed' => 'لم ينجح المهتدي في اختبار نهائي معتمد لهذا البرنامج.',
        'already_exists' => 'توجد شهادة من هذا النوع قيد الإجراء أو صادرة لهذا المهتدي.',
        'convert_unavailable' => 'اختر مهتديًا في هذا المركز.',
        'invalid_transition' => 'لا يمكن نقل الشهادة من :from إلى :to.',
        'self_approval' => 'يجب أن يعتمد الشهادة شخص غير مقدم الطلب.',
        'reason_required' => 'السبب مطلوب.',
        'no_template' => 'لا يوجد قالب معتمد ومفعّل لهذا النوع.',
        'template_not_editable' => 'يُغيَّر القالب المعتمد بإنشاء إصدار جديد.',
    ],
];
