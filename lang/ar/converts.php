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
        'invalid_identity_number' => 'رقم الهوية غير صالح للنوع المختار.',
        'issuing_country_required' => 'اختر جهة الإصدار.',
        'search_limit' => 'تم بلوغ حد البحث. حاول مجددًا بعد :minutes دقيقة.',
        'already_registered' => 'هذه الهوية مسجلة مسبقًا لمهتدٍ.',
    ],
];
