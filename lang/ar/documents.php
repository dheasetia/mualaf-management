<?php

declare(strict_types=1);

return [
    'model' => 'مستند',
    'plural' => 'المستندات',
    'letters' => ['model' => 'خطاب', 'plural' => 'الخطابات'],
    'templates' => ['model' => 'قالب خطاب', 'plural' => 'قوالب الخطابات'],
    'document_types' => [
        'iqamah_copy' => 'صورة الإقامة', 'passport_copy' => 'صورة الجواز', 'photo' => 'صورة شخصية',
        'medical_letter' => 'خطاب طبي', 'receipt' => 'إيصال', 'dawah_material' => 'مادة دعوية', 'other' => 'أخرى',
    ],
    'letter_types' => [
        'shahadah_certificate' => 'شهادة إشهار الإسلام', 'new_convert_data_letter' => 'خطاب بيانات مهتدٍ جديد',
        'khitan_referral_letter' => 'خطاب إحالة للختان', 'religion_update_letter' => 'خطاب دعم تحديث بيانات الديانة',
        'guidance_certificate' => 'شهادة التأهيل', 'other_letter' => 'خطاب عام',
    ],
    'verification_statuses' => ['pending' => 'بانتظار التحقق', 'verified' => 'تم التحقق', 'rejected' => 'مرفوض'],
    'generated_statuses' => ['draft' => 'مسودة', 'pending_approval' => 'بانتظار الاعتماد', 'issued' => 'صادر', 'cancelled' => 'ملغى'],
    'fields' => [
        'type' => 'النوع', 'file' => 'الملف', 'filename' => 'اسم الملف', 'version' => 'الإصدار', 'status' => 'الحالة',
        'uploaded_at' => 'تاريخ الرفع', 'reason' => 'السبب', 'number' => 'الرقم', 'addressee' => 'الجهة الموجه إليها',
        'body' => 'نص إضافي', 'issued_on' => 'تاريخ الإصدار', 'pdf' => 'ملف PDF', 'template_code' => 'الرمز',
        'name' => 'الاسم', 'description' => 'الوصف', 'html_template' => 'قالب HTML', 'orientation' => 'الاتجاه',
        'active' => 'مفعّل', 'approved' => 'معتمد',
    ],
    'help' => [
        'file' => 'PDF أو JPEG أو PNG أو WebP، بحد أقصى 10 ميجابايت. تُحذف بيانات الموقع من الصور.',
        'html_template' => 'استخدم حقول اللقطة بالصيغة {{ field }}، مثل {{ full_name }} و{{ document_number }} و{{ addressee }} و{{ body }}. لا يُنفَّذ أي شيء آخر في القالب.',
    ],
    'pdf' => ['ready' => 'جاهز', 'processing' => 'قيد الإنشاء'],
    'actions' => [
        'upload' => 'رفع مستند', 'new_version' => 'رفع إصدار جديد', 'verify' => 'تحقق', 'reject' => 'رفض',
        'download' => 'تنزيل', 'generate' => 'إنشاء خطاب', 'cancel' => 'إلغاء الخطاب',
        'new_template_version' => 'إصدار جديد', 'approve_template' => 'اعتماد وتفعيل',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'file_type' => 'تُقبل ملفات PDF أو JPEG أو PNG أو WebP فقط.',
        'file_too_large' => 'حجم الملف أكبر من :mb ميجابايت.',
        'already_decided' => 'تم التحقق من هذا المستند أو رفضه مسبقًا.',
        'own_upload' => 'يجب أن يتحقق شخص غير من رفع المستند.',
        'reason_required' => 'السبب مطلوب.',
        'shahadah_not_verified' => 'يجب التحقق من إشهار الإسلام أولًا.',
        'letter_not_for_convert' => 'تصدر الشهادات من شاشة الشهادات.',
        'khitan_male_only' => 'خطاب الإحالة للختان للمهتدين الذكور فقط.',
        'no_template' => 'لا يوجد قالب معتمد ومفعّل لهذا النوع من الخطابات.',
        'not_issued' => 'لا يُلغى إلا خطاب صادر.',
        'self_approval' => 'يجب أن يعتمد القالب شخص غير من أعده.',
        'template_not_editable' => 'يُغيَّر القالب المعتمد بإنشاء إصدار جديد.',
    ],
];
