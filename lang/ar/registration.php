<?php

declare(strict_types=1);

return [
    'model' => 'تسجيل',
    'plural' => 'التسجيلات',
    'page_title' => 'تسجيل الشهادة',
    'sections' => [
        'source' => 'مدعو أو حضور مباشر',
        'identity' => 'وثيقة الهوية',
        'profile' => 'المهتدي',
        'shahadah' => 'الشهادة',
        'contact' => 'التواصل',
        'witnesses' => 'الشهود',
    ],
    'fields' => [
        'prospect' => 'المدعو (مستعد للشهادة)',
        'primary_dai' => 'الداعية الأساسي',
        'shahadah_date' => 'تاريخ الشهادة',
        'shahadah_time' => 'الوقت',
        'location' => 'المكان',
        'city' => 'المدينة',
        'preacher' => 'الداعية الحاضر',
        'notes' => 'ملاحظات',
        'registration_no' => 'رقم التسجيل',
        'registration_date' => 'تاريخ التسجيل',
        'convert' => 'المهتدي',
        'status' => 'الحالة',
        'registered_by' => 'سجّله',
        'witnesses' => 'الشهود',
        'witness_name' => 'الاسم',
        'witness_phone' => 'الجوال',
        'witness_identity_last4' => 'آخر 4 أرقام من الهوية',
        'witness_relationship' => 'الصلة بالمهتدي',
    ],
    'help' => [
        'prospect' => 'اتركه فارغًا للحضور المباشر، ثم اختر الداعية الأساسي.',
        'witnesses' => 'الأشخاص الذين شهدوا النطق بالشهادة، مع رقم جوال لكل منهم.',
    ],
    'actions' => [
        'register' => 'تسجيل',
        'verify' => 'تحقق',
        'register_shahadah' => 'تسجيل الشهادة',
        'add_witness' => 'إضافة شاهد',
    ],
    'notifications' => [
        'registered' => 'تم التسجيل برقم :number بانتظار التحقق.',
        'verified' => 'تم التحقق من الشهادة.',
    ],
    'registration_statuses' => [
        'draft' => 'مسودة', 'pending_verification' => 'بانتظار التحقق', 'verified' => 'تم التحقق',
        'rejected' => 'مرفوض', 'voided' => 'ملغى',
    ],
    'shahadah_statuses' => [
        'recorded' => 'مسجلة', 'verified' => 'تم التحقق', 'voided' => 'ملغاة',
    ],
    'record_types' => [
        'first' => 'الشهادة الأولى', 'renewal_ceremony' => 'تجديد الشهادة',
    ],
    'errors' => [
        'possible_duplicate' => 'قد يكون هذا الشخص مسجلًا مسبقًا. فُتحت حالة مراجعة لرئيس قسم الدعوة؛ أعد التسجيل بعد المراجعة.',
        'primary_dai_required' => 'اختر الداعية الأساسي للحضور المباشر.',
        'date_in_future' => 'لا يمكن أن يكون تاريخ الشهادة في المستقبل.',
        'same_user' => 'يتحقق من التسجيل شخص غير الذي سجّله.',
        'not_pending' => 'هذا التسجيل ليس بانتظار التحقق.',
        'witnesses_required' => '{1} سجّل شاهدًا واحدًا على الأقل.|[2,*] سجّل :count شهود على الأقل.',
        'too_many_witnesses' => 'سجّل :count شهود كحد أقصى.',
        'invalid_witness' => 'لكل شاهد اسم ورقم جوال صحيح، وآخر أرقام الهوية أربعة أرقام.',
        'duplicate_witness' => 'الشاهد نفسه مذكور مرتين.',
    ],
];
