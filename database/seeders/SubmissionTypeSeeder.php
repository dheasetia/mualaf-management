<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\SubmissionType;
use Illuminate\Database\Seeder;

/**
 * 10 §10.1 seed types. Each completed submission issues the matching
 * letter (generates_document_template_code is the letter type).
 */
final class SubmissionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $iqamah = ['code' => 'iqamah_copy', 'name_ar' => 'صورة الإقامة أو الهوية', 'name_en' => 'Iqamah or ID copy', 'required' => true];
        $photo = ['code' => 'photo', 'name_ar' => 'صورة شخصية', 'name_en' => 'Photo', 'required' => false];

        $types = [
            'new_convert_data' => ['تقديم بيانات مهتدٍ جديد', 'New convert data submission', [$iqamah, $photo], 'new_convert_data_letter'],
            'khitan_referral' => ['طلب خطاب إحالة للختان', 'Circumcision referral request', [$iqamah, ['code' => 'medical_letter', 'name_ar' => 'تقرير طبي', 'name_en' => 'Medical report', 'required' => false]], 'khitan_referral_letter'],
            'religion_update' => ['طلب خطاب دعم تحديث بيانات الديانة', 'Religion update support request', [$iqamah], 'religion_update_letter'],
            'other_letter' => ['طلب خطاب آخر', 'Other letter request', [$iqamah], 'other_letter'],
        ];

        foreach ($types as $code => [$nameAr, $nameEn, $documents, $letter]) {
            SubmissionType::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'required_documents' => $documents,
                'workflow_config' => ['approval_stages' => 1, 'sla_days' => 5],
                'generates_document_template_code' => $letter,
                'active' => true,
            ]);
        }
    }
}
