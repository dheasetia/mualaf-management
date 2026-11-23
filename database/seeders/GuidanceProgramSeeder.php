<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Guidance\Models\GuidanceLesson;
use App\Domain\Guidance\Models\GuidanceModule;
use App\Domain\Guidance\Models\GuidanceProgram;
use Illuminate\Database\Seeder;

/**
 * The national default program (PRD §19), a template offices copy. Runs
 * without tenant context, so it only creates the national program once.
 */
final class GuidanceProgramSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: list<string>}> module => [Arabic title, lessons] */
    private const array MODULES = [
        'Aqidah' => ['العقيدة', ['أركان الإيمان', 'التوحيد وأقسامه', 'معنى لا إله إلا الله']],
        'Shahadah' => ['الشهادتان', ['معنى الشهادتين', 'شروط لا إله إلا الله', 'نواقض الإسلام']],
        'Thaharah' => ['الطهارة', ['أنواع المياه', 'النجاسات وإزالتها', 'الغسل']],
        'Wudhu' => ['الوضوء', ['فرائض الوضوء', 'سنن الوضوء', 'نواقض الوضوء', 'التيمم']],
        'Shalat' => ['الصلاة', ['أوقات الصلاة', 'أركان الصلاة وواجباتها', 'صفة الصلاة عمليًا', 'صلاة الجمعة والجماعة']],
        'Quran' => ['القرآن الكريم', ['سورة الفاتحة', 'قصار السور', 'آداب تلاوة القرآن']],
        'Dua' => ['الأدعية والأذكار', ['أذكار الصلاة', 'أذكار الصباح والمساء', 'أدعية يومية']],
        'Ibadah' => ['العبادات', ['الصيام', 'الزكاة', 'الحج والعمرة']],
        'Akhlaq' => ['الأخلاق', ['حسن الخلق', 'بر الوالدين وصلة الرحم', 'حقوق الجار والزوجة']],
        'Muamalah' => ['المعاملات الأساسية', ['الحلال والحرام في الطعام والشراب', 'المعاملات المالية', 'التعامل مع غير المسلمين']],
    ];

    public function run(): void
    {
        if (GuidanceProgram::query()->whereNull('tenant_id')->where('code', 'NEW-MUSLIM')->exists()) {
            return;
        }

        $program = new GuidanceProgram;
        $program->code = 'NEW-MUSLIM';
        $program->name_ar = 'برنامج تأهيل المهتدين الجدد';
        $program->name_en = 'New Muslim foundation program';
        $program->duration_days = 90;
        $program->save();

        $order = 0;

        foreach (self::MODULES as $titleEn => [$titleAr, $lessons]) {
            $module = new GuidanceModule;
            $module->program_id = $program->id;
            $module->title = "{$titleAr} ({$titleEn})";
            $module->sort_order = $order++;
            $module->save();

            foreach ($lessons as $position => $lessonTitle) {
                $lesson = new GuidanceLesson;
                $lesson->module_id = $module->id;
                $lesson->title = $lessonTitle;
                $lesson->sort_order = $position;
                $lesson->save();
            }
        }
    }
}
