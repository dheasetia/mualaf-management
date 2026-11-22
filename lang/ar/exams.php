<?php

declare(strict_types=1);

return [
    'model' => 'اختبار',
    'plural' => 'الاختبارات',
    'attempts' => 'المختبَرون',
    'fields' => [
        'title' => 'العنوان', 'program' => 'البرنامج', 'class' => 'الفصل', 'exam_type' => 'النوع', 'maximum_score' => 'الدرجة العظمى',
        'passing_score' => 'درجة النجاح', 'scheduled_at' => 'الموعد', 'status' => 'الحالة', 'components' => 'المكونات',
        'component_code' => 'الرمز', 'name' => 'الاسم', 'max_score' => 'العظمى', 'weight' => 'الوزن', 'enrollments' => 'المختبَرون',
        'convert' => 'المهتدي', 'attempt_number' => 'المحاولة', 'final_score' => 'الدرجة النهائية', 'passed' => 'ناجح',
        'result_status' => 'النتيجة', 'reason' => 'السبب', 'score' => 'الدرجة',
    ],
    'actions' => [
        'start' => 'بدء', 'cancel' => 'إلغاء الاختبار', 'submit' => 'إرسال للاعتماد', 'reopen' => 'إعادة للتصحيح',
        'finalize' => 'اعتماد', 'add_attempts' => 'إضافة مختبَرين', 'grade' => 'رصد الدرجات', 'absent' => 'غائب', 'amend' => 'تعديل النتيجة',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'types' => ['evaluation' => 'تقييم', 'practical' => 'عملي', 'monthly' => 'شهري', 'final' => 'نهائي', 'remedial' => 'إعادة'],
    'statuses' => ['scheduled' => 'مجدول', 'in_progress' => 'جارٍ', 'graded' => 'مصحح', 'pending_finalization' => 'بانتظار الاعتماد', 'finalized' => 'معتمد', 'cancelled' => 'ملغى'],
    'attempt_statuses' => ['scheduled' => 'مجدولة', 'absent' => 'غائب', 'in_progress' => 'جارية', 'graded' => 'مصححة', 'finalized' => 'معتمدة'],
    'result_statuses' => ['provisional' => 'مبدئية', 'finalized' => 'نهائية', 'amended' => 'معدّلة'],
    'errors' => [
        'program_unavailable' => 'اختر برنامجًا متاحًا لهذا المركز.',
        'class_not_in_program' => 'يجب أن يتبع الفصل برنامج الاختبار.',
        'components_required' => 'أضف مكونًا واحدًا على الأقل.',
        'passing_above_maximum' => 'لا يمكن أن تتجاوز درجة النجاح الدرجة العظمى.',
        'not_editable' => 'لا يمكن تعديل الاختبار في هذه المرحلة.',
        'invalid_transition' => 'لا يمكن نقل الاختبار من :from إلى :to.',
        'enrollment_not_eligible' => 'يجب أن يكون المختبَرون مسجلين في برنامج الاختبار (وفصله).',
        'attempt_open' => 'لهذا المختبَر محاولة مفتوحة بالفعل.',
        'already_passed' => 'نجح هذا المختبَر في هذا الاختبار بالفعل.',
        'not_your_class' => 'لا يصحح هذه المحاولة إلا معلمو فصل المختبَر.',
        'scores_incomplete' => 'أدخل درجة لكل مكون.',
        'score_out_of_range' => 'درجة :component خارج النطاق.',
        'not_graded' => 'صحح جميع المحاولات أولًا.',
        'reason_required' => 'السبب مطلوب.',
        'not_finalized' => 'تُعدَّل النتائج المعتمدة فقط؛ غيّر الدرجات المبدئية بإعادة التصحيح.',
    ],
];
