<?php

declare(strict_types=1);

return [
    'identity_types' => [
        'national_id' => 'الهوية الوطنية',
        'iqamah' => 'الإقامة',
        'passport' => 'جواز السفر',
        'gcc_id' => 'هوية خليجية',
        'border_number' => 'رقم الحدود',
        'other' => 'أخرى',
    ],
    'model' => 'مهتدٍ',
    'plural' => 'المهتدون',
    'alias' => 'مهتدٍ :code',
    'statuses' => [
        'active' => 'نشط', 'inactive' => 'غير نشط', 'deceased' => 'متوفى', 'record_closed' => 'سجل مغلق',
    ],
    'disclosure_levels' => [
        'normal' => 'عادي', 'restricted' => 'مقيد', 'high_confidentiality' => 'سري للغاية',
    ],
    'contact_types' => [
        'mobile' => 'جوال', 'whatsapp' => 'واتساب', 'email' => 'بريد إلكتروني', 'telegram' => 'تيليجرام', 'other' => 'أخرى',
    ],
    'sections' => [
        'profile' => 'البيانات الشخصية', 'sensitive' => 'الخلفية الدينية', 'identifiers' => 'وثائق الهوية',
        'contacts' => 'وسائل التواصل', 'history' => 'سجل حالة الملف',
    ],
    'fields' => [
        'full_name' => 'الاسم الكامل', 'name_ar' => 'الاسم (عربي)', 'previous_name' => 'الاسم السابق',
        'islamic_name' => 'الاسم الإسلامي', 'display_name' => 'الاسم', 'gender' => 'الجنس', 'date_of_birth' => 'تاريخ الميلاد',
        'nationality' => 'الجنسية', 'native_language' => 'اللغة الأم', 'previous_religion' => 'الديانة السابقة',
        'previous_religion_other' => 'الديانة السابقة (أخرى)', 'status' => 'حالة الملف',
        'disclosure_risk_level' => 'مستوى الإفصاح', 'primary_dai' => 'الداعية الأساسي', 'origin_office' => 'مركز التسجيل',
        'reason' => 'السبب', 'contact_type' => 'النوع', 'value' => 'وسيلة التواصل', 'is_primary' => 'أساسي',
        'identity_type' => 'النوع', 'identity_number' => 'الرقم', 'issuing_country' => 'جهة الإصدار',
        'verification_status' => 'التحقق', 'changed_by' => 'بواسطة', 'changed_at' => 'التاريخ',
    ],
    'actions' => [
        'change_disclosure' => 'تغيير مستوى الإفصاح', 'change_status' => 'تغيير حالة الملف',
        'assign_dai' => 'إسناد داعية', 'add_contact' => 'إضافة وسيلة تواصل', 'remove_contact' => 'حذف', 'reveal' => 'إظهار الرقم',
    ],
    'notifications' => [
        'saved' => 'تم الحفظ.', 'revealed' => 'الرقم: :number (تم تسجيل هذا العرض)',
    ],
    'job_statuses' => ['employed' => 'على رأس العمل', 'ended' => 'انتهى', 'unknown' => 'غير معروف'],
    'residence_types' => [
        'company_dormitory' => 'سكن الشركة', 'workers_camp' => 'مخيم عمال', 'house' => 'منزل',
        'apartment' => 'شقة', 'other' => 'أخرى',
    ],
    'contact_roles' => [
        'dormitory_head' => 'مسؤول السكن', 'hr' => 'الموارد البشرية', 'public_relations' => 'العلاقات العامة',
        'supervisor' => 'المشرف', 'friend' => 'صديق', 'family' => 'العائلة', 'other' => 'أخرى',
    ],
    'disclosure_scopes' => [
        'none' => 'لا يعلم شيئًا', 'general_support' => 'دعم عام فقط', 'knows_conversion' => 'يعلم بإسلامه',
    ],
    'locations' => [
        'employments' => 'العمل', 'residences' => 'السكن', 'contact_persons' => 'جهات الاتصال',
        'company' => 'الشركة', 'company_name_free' => 'الشركة (خارج الدليل)', 'occupation' => 'المهنة',
        'department' => 'القسم', 'employee_number' => 'الرقم الوظيفي', 'work_location' => 'موقع العمل',
        'work_city' => 'مدينة العمل', 'work_phone' => 'هاتف العمل', 'employment_status' => 'الحالة',
        'started_at' => 'من', 'ended_at' => 'إلى', 'is_current' => 'حالي',
        'residence_type' => 'النوع', 'name' => 'الاسم', 'city' => 'المدينة', 'address' => 'العنوان', 'district' => 'الحي',
        'building_no' => 'المبنى', 'room_no' => 'الغرفة', 'is_pickup_point' => 'نقطة التقاء',
        'pickup_instructions' => 'تعليمات الالتقاء', 'latitude' => 'خط العرض', 'longitude' => 'خط الطول',
        'google_maps_url' => 'رابط خرائط Google', 'notes' => 'ملاحظات', 'position' => 'المنصب', 'contact_role' => 'الصفة',
        'phone' => 'الهاتف', 'alternate_phone' => 'هاتف بديل', 'email' => 'البريد الإلكتروني', 'preferred_language' => 'اللغة',
        'permission_to_contact' => 'يسمح بالتواصل', 'disclosure_scope' => 'ما يعلمه', 'is_primary' => 'أساسي',
    ],
    'search' => [
        'title' => 'البحث الوطني بالهوية',
        'description' => 'يتحقق مما إذا كان الشخص مسجلًا كمهتدٍ في أي مركز. تُسجَّل عمليات البحث ويُسمح بـ :max عملية في الساعة.',
        'identity_type' => 'نوع الهوية',
        'issuing_country' => 'جهة الإصدار',
        'identity_number' => 'رقم الهوية',
        'submit' => 'بحث',
        'not_found' => 'غير مسجل. لا يوجد سجل مهتدٍ بهذه الهوية.',
        'found_here' => 'مسجل مسبقًا في هذا المركز.',
        'found_elsewhere' => 'مسجل مسبقًا في :office.',
        'withheld' => 'مسجل في مركز آخر. يُرجى التواصل مع الإدارة المركزية.',
        'name' => 'الاسم: :name',
    ],
    'errors' => [
        'invalid_maps_url' => 'استخدم رابط https من خرائط Google.',
        'reason_required' => 'السبب مطلوب.',
        'invalid_transition' => 'لا يمكن تغيير حالة الملف من :from إلى :to.',
        'closed' => 'هذا الملف مغلق ولا يمكن تعديله.',
        'invalid_contact' => 'وسيلة التواصل غير صالحة للنوع المختار.',
        'invalid_identity_number' => 'رقم الهوية غير صالح للنوع المختار.',
        'issuing_country_required' => 'اختر جهة الإصدار.',
        'search_limit' => 'تم بلوغ حد البحث. حاول مجددًا بعد :minutes دقيقة.',
        'already_registered' => 'هذه الهوية مسجلة مسبقًا لمهتدٍ.',
    ],
];
