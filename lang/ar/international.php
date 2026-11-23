<?php

declare(strict_types=1);

return [
    'navigation_group' => 'المتابعة الدولية',
    'consents' => ['model' => 'موافقة', 'plural' => 'الموافقات'],
    'home_contacts' => ['model' => 'جهة اتصال في البلد الأصلي', 'plural' => 'جهات الاتصال في البلد الأصلي'],
    'home_locations' => ['model' => 'عنوان في البلد الأصلي', 'plural' => 'العناوين في البلد الأصلي'],
    'organizations' => ['model' => 'جهة إسلامية في الخارج', 'plural' => 'الجهات الإسلامية في الخارج'],
    'referrals' => ['model' => 'إحالة', 'plural' => 'الإحالات الدولية'],
    'checklists' => ['model' => 'قائمة ما قبل السفر', 'plural' => 'قوائم ما قبل السفر'],
    'consent_types' => [
        'international_sharing' => 'المشاركة مع جهة متابعة في الخارج', 'contact_employer' => 'التواصل مع جهة العمل', 'contact_family' => 'التواصل مع الأسرة',
        'photo_use' => 'استخدام الصور', 'pilgrimage_participation' => 'المشاركة في العمرة/الحج',
    ],
    'consent_methods' => ['written' => 'كتابية', 'verbal_witnessed' => 'شفهية بحضور شاهد', 'digital' => 'رقمية'],
    'relationships' => [
        'spouse' => 'زوج/زوجة', 'parent' => 'أحد الوالدين', 'sibling' => 'أخ/أخت', 'child' => 'ابن/ابنة', 'relative' => 'قريب', 'friend' => 'صديق',
        'muslim_contact' => 'جهة اتصال مسلمة', 'local_dai' => 'داعية محلي', 'other' => 'أخرى',
    ],
    'organization_types' => ['mosque' => 'مسجد', 'islamic_center' => 'مركز إسلامي', 'dawah_org' => 'جهة دعوية', 'school' => 'مدرسة', 'individual_dai' => 'داعية فرد'],
    'organization_statuses' => ['unverified' => 'غير موثقة', 'verified' => 'موثقة', 'inactive' => 'غير نشطة'],
    'shared_fields' => [
        'name' => 'الاسم', 'islamic_name' => 'الاسم الإسلامي', 'gender' => 'الجنس', 'shahadah_date' => 'تاريخ إشهار الإسلام', 'guidance_status' => 'حالة التأهيل',
        'languages' => 'اللغات', 'contact' => 'جهة اتصال (موافقة)', 'home_city' => 'المدينة في البلد الأصلي', 'support_needed' => 'الدعم المطلوب',
    ],
    'referral_statuses' => [
        'draft' => 'مسودة', 'pending_consent' => 'بانتظار الموافقة', 'ready' => 'جاهزة للإرسال', 'sent' => 'مرسلة',
        'acknowledged' => 'تم الاستلام', 'active' => 'نشطة', 'closed' => 'مغلقة', 'declined' => 'مرفوضة',
    ],
    'checklist_statuses' => ['open' => 'مفتوحة', 'completed' => 'مكتملة', 'cancelled' => 'ملغاة'],
    'checklist_items' => [
        'contact_confirmed' => 'تأكيد وسيلة التواصل', 'address_confirmed' => 'تأكيد العنوان', 'mosque_identified' => 'تحديد المسجد',
        'local_dai_identified' => 'تحديد الداعية المحلي', 'consent_obtained' => 'الحصول على الموافقة', 'referral_sent' => 'إرسال الإحالة',
        'acknowledgment_received' => 'استلام التأكيد', 'follow_up_scheduled' => 'جدولة المتابعة',
    ],
    'item_statuses' => ['pending' => 'قيد الانتظار', 'done' => 'تم', 'not_applicable' => 'لا ينطبق'],
    'fields' => [
        'consent_type' => 'النوع', 'fields' => 'المعلومات المشمولة', 'destination_country' => 'بلد الوجهة', 'given_at' => 'تاريخ الموافقة',
        'method' => 'الطريقة', 'witnessed_by' => 'الشاهد', 'expires_at' => 'تنتهي في', 'status' => 'الحالة', 'reason' => 'السبب',
        'country' => 'البلد', 'contact_name' => 'الاسم', 'relationship' => 'الصلة', 'phone' => 'الهاتف', 'whatsapp' => 'واتساب', 'email' => 'البريد الإلكتروني',
        'address' => 'العنوان', 'city' => 'المدينة', 'province_state' => 'المقاطعة/الولاية', 'district' => 'الحي', 'village' => 'القرية',
        'is_muslim' => 'مسلم', 'is_primary' => 'رئيسي', 'permission_to_contact' => 'وافق على التواصل', 'disclosure_scope' => 'معرفته بالإسلام',
        'notes' => 'ملاحظات', 'convert' => 'المهتدي', 'destination_city' => 'مدينة الوجهة', 'organization' => 'الجهة في الخارج',
        'external_contact_name' => 'جهة الاتصال في الخارج', 'external_contact_phone' => 'هاتف جهة الاتصال', 'referral_reason' => 'سبب الإحالة',
        'information_scope' => 'المعلومات المشاركة', 'sent_at' => 'تاريخ الإرسال', 'packet' => 'المعلومات المشاركة', 'organization_name' => 'الاسم',
        'organization_type' => 'النوع', 'contact_person' => 'المسؤول', 'website' => 'الموقع', 'verification_status' => 'التوثيق',
        'expected_departure_on' => 'موعد السفر المتوقع', 'item' => 'البند', 'latitude' => 'خط العرض', 'longitude' => 'خط الطول', 'google_maps_url' => 'رابط خرائط جوجل',
    ],
    'actions' => [
        'record_consent' => 'تسجيل موافقة', 'withdraw' => 'سحب', 'add' => 'إضافة', 'create_referral' => 'إحالة جديدة', 'check_consent' => 'التحقق من الموافقة',
        'send' => 'إرسال', 'acknowledge' => 'تم الاستلام', 'activate' => 'نشطة', 'decline' => 'مرفوضة', 'close' => 'إغلاق', 'packet' => 'المعلومات المشاركة',
        'open_checklist' => 'فتح قائمة', 'done' => 'تم', 'not_applicable' => 'لا ينطبق', 'reset' => 'قيد الانتظار', 'complete' => 'إكمال',
        'cancel' => 'إلغاء', 'verify' => 'توثيق', 'deactivate' => 'تعطيل', 'open' => 'فتح',
    ],
    'notifications' => ['saved' => 'تم الحفظ.'],
    'errors' => [
        'invalid_transition' => 'لا يمكن الانتقال من :from إلى :to.',
        'reason_required' => 'السبب مطلوب.',
        'consent_missing' => 'لا توجد موافقة سارية لمشاركة هذه المعلومات في الخارج؛ الإحالة بانتظار الموافقة.',
        'scope_required' => 'اختر المعلومات المشمولة.',
        'already_withdrawn' => 'سُحبت هذه الموافقة مسبقًا.',
        'witness_required' => 'الموافقة الشفهية تحتاج شاهدًا غيرك.',
        'checklist_open' => 'لهذا المهتدي قائمة مفتوحة.',
        'items_pending' => 'سجّل كل بند (تم أو لا ينطبق) أولًا.',
        'organization_unavailable' => 'اختر جهة نشطة من الدليل.',
    ],
];
