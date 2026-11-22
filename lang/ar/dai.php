<?php

declare(strict_types=1);

return [
    'model' => 'داعية',
    'plural' => 'الدعاة',
    'navigation_group' => 'الدعوة',
    'sections' => [
        'profile' => 'البيانات الشخصية',
        'contact' => 'التواصل والحساب',
        'employment' => 'العمل والإشراف',
        'languages' => 'اللغات',
    ],
    'fields' => [
        'employee_number' => 'الرقم الوظيفي',
        'full_name' => 'الاسم الكامل',
        'full_name_ar' => 'الاسم الكامل (عربي)',
        'gender' => 'الجنس',
        'nationality' => 'الجنسية',
        'education' => 'المؤهل العلمي',
        'specialties' => 'التخصصات',
        'phone' => 'الجوال',
        'email' => 'البريد الإلكتروني',
        'user' => 'حساب المستخدم',
        'employment_status' => 'نوع التوظيف',
        'joined_at' => 'تاريخ الالتحاق',
        'supervisor' => 'المشرف',
        'languages' => 'اللغات',
        'language' => 'اللغة',
        'proficiency' => 'مستوى الإتقان',
        'status' => 'الحالة',
        'reason' => 'السبب',
    ],
    'help' => [
        'user' => 'اربطه إذا كان الداعية يدخل إلى النظام. تظهر فقط حسابات أعضاء هذا المركز.',
    ],
    'actions' => [
        'add_language' => 'إضافة لغة',
        'change_status' => 'تغيير الحالة',
    ],
    'notifications' => [
        'status_changed' => 'تم تغيير الحالة.',
    ],
    'statuses' => [
        'active' => 'نشط',
        'on_leave' => 'في إجازة',
        'inactive' => 'غير نشط',
    ],
    'employment_statuses' => [
        'full_time' => 'دوام كامل',
        'part_time' => 'دوام جزئي',
        'volunteer' => 'متطوع',
        'contract' => 'متعاقد',
    ],
    'proficiencies' => [
        'native' => 'اللغة الأم',
        'fluent' => 'بطلاقة',
        'working' => 'جيد',
        'basic' => 'مبتدئ',
    ],
    'workload' => [
        'title' => 'عبء عمل الدعاة',
        'primary_prospects' => 'المدعوون (أساسي)',
        'primary_converts' => 'المهتدون (أساسي)',
        'other_assignments' => 'تكليفات أخرى',
        'supervisees' => 'الدعاة تحت الإشراف',
    ],
    'follow_up_types' => [
        'visit' => 'زيارة', 'call' => 'اتصال', 'message' => 'رسالة', 'discussion' => 'نقاش',
        'dialogue' => 'حوار', 'class' => 'درس', 'meeting' => 'لقاء', 'other' => 'أخرى',
    ],
    'contact_methods' => [
        'in_person' => 'حضوريًا', 'phone' => 'هاتف', 'whatsapp' => 'واتساب', 'video' => 'مكالمة مرئية',
        'email' => 'بريد إلكتروني', 'other' => 'أخرى',
    ],
    'confidentiality' => [
        'normal' => 'عادي', 'confidential' => 'سري',
    ],
    'errors' => [
        'assignee_unavailable' => 'يجب أن يكون الداعية نشطًا في المركز المسؤول عن هذا الشخص.',
        'not_a_dai' => 'يسجل المتابعات فقط المستخدمون الذين لديهم سجل داعية في هذا المركز.',
        'duplicate_employee_number' => 'يوجد داعية آخر في هذا المركز بنفس الرقم الوظيفي.',
        'user_already_linked' => 'حساب المستخدم هذا مرتبط بداعية آخر في هذا المركز.',
        'user_not_member' => 'يجب أن يكون حساب المستخدم عضوًا نشطًا في هذا المركز.',
        'supervisor_self' => 'لا يمكن للداعية أن يشرف على نفسه.',
        'supervisor_cycle' => 'هذا المشرف يقع تحت إشراف هذا الداعية بشكل مباشر أو غير مباشر.',
        'supervisor_unavailable' => 'يجب أن يكون المشرف داعية نشطًا في هذا المركز.',
        'invalid_transition' => 'لا يمكن تغيير الحالة من :from إلى :to.',
        'has_active_primary' => 'هذا الداعية ما زال الداعية الأساسي لـ :count أشخاص. أعد إسنادهم أولًا.',
    ],
];
