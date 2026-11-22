<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Enums\DaiActivityCategory;
use App\Domain\Platform\Models\BudgetCategory;
use App\Domain\Platform\Models\City;
use App\Domain\Platform\Models\Country;
use App\Domain\Platform\Models\DaiActivityType;
use App\Domain\Platform\Models\Language;
use App\Domain\Platform\Models\Region;
use App\Domain\Platform\Models\Religion;
use Illuminate\Database\Seeder;
use Locale;

/**
 * Initial national master data (06-data-model-core §6.1).
 *
 * Only inserts what is missing, matched by code: once Central administrators
 * edit or deactivate an entry, running the seeder again does not undo it.
 */
final class PlatformMasterSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}> */
    private const array REGIONS = [
        'RD' => ['منطقة الرياض', 'Riyadh Region', [
            'RUH' => ['الرياض', 'Riyadh'],
            'KHJ' => ['الخرج', 'Al-Kharj'],
            'DWD' => ['الدوادمي', 'Ad-Dawadmi'],
        ]],
        'MK' => ['منطقة مكة المكرمة', 'Makkah Region', [
            'MKK' => ['مكة المكرمة', 'Makkah'],
            'JED' => ['جدة', 'Jeddah'],
            'TIF' => ['الطائف', 'Taif'],
            'RAB' => ['رابغ', 'Rabigh'],
        ]],
        'MD' => ['منطقة المدينة المنورة', 'Madinah Region', [
            'MED' => ['المدينة المنورة', 'Madinah'],
            'YNB' => ['ينبع', 'Yanbu'],
            'ULA' => ['العلا', 'AlUla'],
        ]],
        'EP' => ['المنطقة الشرقية', 'Eastern Province', [
            'DMM' => ['الدمام', 'Dammam'],
            'KHB' => ['الخبر', 'Khobar'],
            'DHA' => ['الظهران', 'Dhahran'],
            'JUB' => ['الجبيل', 'Jubail'],
            'HOF' => ['الأحساء', 'Al-Ahsa'],
            'QTF' => ['القطيف', 'Qatif'],
            'HFB' => ['حفر الباطن', 'Hafar Al-Batin'],
        ]],
        'QS' => ['منطقة القصيم', 'Qassim Region', [
            'BRD' => ['بريدة', 'Buraydah'],
            'UNZ' => ['عنيزة', 'Unaizah'],
            'RAS' => ['الرس', 'Ar-Rass'],
        ]],
        'AS' => ['منطقة عسير', 'Asir Region', [
            'AHB' => ['أبها', 'Abha'],
            'KMS' => ['خميس مشيط', 'Khamis Mushait'],
            'BSH' => ['بيشة', 'Bisha'],
        ]],
        'TB' => ['منطقة تبوك', 'Tabuk Region', [
            'TUU' => ['تبوك', 'Tabuk'],
        ]],
        'HA' => ['منطقة حائل', 'Hail Region', [
            'HAS' => ['حائل', 'Hail'],
        ]],
        'NB' => ['منطقة الحدود الشمالية', 'Northern Borders Region', [
            'RAE' => ['عرعر', 'Arar'],
        ]],
        'JZ' => ['منطقة جازان', 'Jazan Region', [
            'GIZ' => ['جازان', 'Jazan'],
        ]],
        'NJ' => ['منطقة نجران', 'Najran Region', [
            'EAM' => ['نجران', 'Najran'],
        ]],
        'BA' => ['منطقة الباحة', 'Al-Bahah Region', [
            'ABT' => ['الباحة', 'Al-Bahah'],
        ]],
        'JF' => ['منطقة الجوف', 'Al-Jawf Region', [
            'AJF' => ['سكاكا', 'Sakaka'],
        ]],
    ];

    /**
     * Languages converts and prospects commonly speak in the Kingdom (ISO 639-1),
     * with the right-to-left ones flagged. Names come from ICU.
     *
     * @var array<string, bool>
     */
    private const array LANGUAGES = [
        'ar' => true, 'en' => false, 'ur' => true, 'hi' => false, 'bn' => false, 'tl' => false,
        'id' => false, 'ms' => false, 'ta' => false, 'te' => false, 'ml' => false, 'kn' => false,
        'si' => false, 'ne' => false, 'pa' => false, 'gu' => false, 'mr' => false, 'fa' => true,
        'ps' => true, 'tr' => false, 'fr' => false, 'es' => false, 'pt' => false, 'de' => false,
        'it' => false, 'ru' => false, 'zh' => false, 'ja' => false, 'ko' => false, 'th' => false,
        'vi' => false, 'my' => false, 'km' => false, 'am' => false, 'ti' => false, 'om' => false,
        'so' => false, 'sw' => false, 'ha' => false, 'yo' => false, 'uz' => false, 'ky' => false,
    ];

    /** Religions a prospect may hold before conversion. @var array<string, array{0: string, 1: string}> */
    private const array RELIGIONS = [
        'christianity' => ['المسيحية', 'Christianity'],
        'hinduism' => ['الهندوسية', 'Hinduism'],
        'buddhism' => ['البوذية', 'Buddhism'],
        'sikhism' => ['السيخية', 'Sikhism'],
        'judaism' => ['اليهودية', 'Judaism'],
        'jainism' => ['الجاينية', 'Jainism'],
        'shinto' => ['الشنتوية', 'Shinto'],
        'taoism' => ['الطاوية', 'Taoism'],
        'confucianism' => ['الكونفوشيوسية', 'Confucianism'],
        'zoroastrianism' => ['الزرادشتية', 'Zoroastrianism'],
        'bahai' => ['البهائية', "Bahá'í"],
        'traditional' => ['الديانات التقليدية', 'Traditional / folk religion'],
        'none' => ['بلا دين', 'No religion'],
        'agnosticism' => ['اللاأدرية', 'Agnosticism'],
        'other' => ['أخرى', 'Other'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const array BUDGET_CATEGORIES = [
        'transport' => ['النقل والمواصلات', 'Transport'],
        'meals' => ['الوجبات والضيافة', 'Meals and hospitality'],
        'venue' => ['المكان والقاعات', 'Venue'],
        'materials' => ['المواد الدعوية', 'Dawah materials'],
        'printing' => ['الطباعة', 'Printing'],
        'gifts' => ['الهدايا', 'Gifts'],
        'honorarium' => ['المكافآت', 'Honorarium'],
        'accommodation' => ['السكن', 'Accommodation'],
        'communication' => ['الاتصالات', 'Communication'],
        'equipment' => ['المعدات', 'Equipment'],
        'other' => ['أخرى', 'Other'],
    ];

    /**
     * PRD §14 activity types: code => [category, name_ar, name_en, requires_verification].
     *
     * @var array<string, array{0: DaiActivityCategory, 1: string, 2: string, 3: bool}>
     */
    private const array ACTIVITY_TYPES = [
        'individual_dawah' => [DaiActivityCategory::Dawah, 'الدعوة الفردية', 'Individual dawah', false],
        'visit' => [DaiActivityCategory::Dawah, 'الزيارة', 'Visit', false],
        'discussion' => [DaiActivityCategory::Dawah, 'النقاش', 'Discussion', false],
        'dialogue' => [DaiActivityCategory::Dawah, 'الحوار', 'Dialogue', false],
        'shahadah' => [DaiActivityCategory::Dawah, 'النطق بالشهادتين', 'Shahadah', true],
        'follow_up' => [DaiActivityCategory::Dawah, 'المتابعة', 'Follow-up', false],
        'class_teaching' => [DaiActivityCategory::Education, 'تدريس الفصول', 'Class teaching', false],
        'lecture' => [DaiActivityCategory::Education, 'الدروس العلمية', 'Lecture', false],
        'quran_teaching' => [DaiActivityCategory::Education, 'تعليم القرآن الكريم', "Qur'an teaching", false],
        'training' => [DaiActivityCategory::Education, 'التدريب', 'Training', false],
        'translation' => [DaiActivityCategory::ScholarlyMedia, 'الترجمة', 'Translation', false],
        'book_writing' => [DaiActivityCategory::ScholarlyMedia, 'تأليف الكتب', 'Book writing', false],
        'article_writing' => [DaiActivityCategory::ScholarlyMedia, 'كتابة المقالات', 'Article writing', false],
        'material_creation' => [DaiActivityCategory::ScholarlyMedia, 'إعداد المواد', 'Material creation', false],
        'material_review' => [DaiActivityCategory::ScholarlyMedia, 'مراجعة المواد', 'Material review', false],
        'audio_video' => [DaiActivityCategory::ScholarlyMedia, 'إنتاج المرئيات والصوتيات', 'Video and audio', false],
        'sports' => [DaiActivityCategory::Social, 'الرياضة', 'Sports', false],
        'tournament' => [DaiActivityCategory::Social, 'البطولات', 'Tournament', false],
        'competition' => [DaiActivityCategory::Social, 'المسابقات', 'Competition', false],
        'gathering' => [DaiActivityCategory::Social, 'اللقاءات', 'Gathering', false],
        'trip' => [DaiActivityCategory::Social, 'الرحلات', 'Trip', false],
        'shared_meal' => [DaiActivityCategory::Social, 'الوجبات الجماعية', 'Shared meal', false],
        'ramadan_activity' => [DaiActivityCategory::Social, 'أنشطة رمضان', 'Ramadan activity', false],
    ];

    public function run(): void
    {
        $this->seedRegionsAndCities();
        $this->seedCountries();
        $this->seedLanguages();

        $order = 0;
        foreach (self::RELIGIONS as $code => [$nameAr, $nameEn]) {
            Religion::query()->firstOrCreate(['code' => $code], ['name_ar' => $nameAr, 'name_en' => $nameEn, 'sort_order' => $order++]);
        }

        foreach (self::BUDGET_CATEGORIES as $code => [$nameAr, $nameEn]) {
            BudgetCategory::query()->firstOrCreate(['code' => $code], ['name_ar' => $nameAr, 'name_en' => $nameEn]);
        }

        foreach (self::ACTIVITY_TYPES as $code => [$category, $nameAr, $nameEn, $requiresVerification]) {
            DaiActivityType::query()->firstOrCreate(['code' => $code], [
                'category' => $category,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'requires_verification' => $requiresVerification,
            ]);
        }
    }

    private function seedRegionsAndCities(): void
    {
        $order = 0;

        foreach (self::REGIONS as $regionCode => [$regionAr, $regionEn, $cities]) {
            $region = Region::withTrashed()->firstOrCreate(
                ['code' => $regionCode],
                ['name_ar' => $regionAr, 'name_en' => $regionEn, 'sort_order' => $order],
            );
            $order++;

            foreach ($cities as $cityCode => [$cityAr, $cityEn]) {
                City::withTrashed()->firstOrCreate(
                    ['code' => $cityCode],
                    ['region_id' => $region->id, 'name_ar' => $cityAr, 'name_en' => $cityEn],
                );
            }
        }
    }

    private function seedCountries(): void
    {
        /** @var array<string, array{0: string, 1: string, 2: string, 3: string|null}> $countries */
        $countries = require database_path('data/countries.php');

        foreach ($countries as $iso2 => [$iso3, $nameAr, $nameEn, $phoneCode]) {
            Country::withTrashed()->firstOrCreate(
                ['iso2' => $iso2],
                ['iso3' => $iso3, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'phone_code' => $phoneCode],
            );
        }
    }

    private function seedLanguages(): void
    {
        foreach (self::LANGUAGES as $code => $isRtl) {
            Language::withTrashed()->firstOrCreate(['code' => $code], [
                'name_ar' => Locale::getDisplayLanguage($code, 'ar'),
                'name_en' => Locale::getDisplayLanguage($code, 'en'),
                'is_rtl' => $isRtl,
            ]);
        }
    }
}
