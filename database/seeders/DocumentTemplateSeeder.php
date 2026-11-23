<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Models\DocumentTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * National default letter templates, version 1, active. Later versions go
 * through ManageDocumentTemplates approval.
 */
final class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'new_convert_data_letter' => 'خطاب بيانات مهتدٍ جديد',
            'khitan_referral_letter' => 'خطاب إحالة للختان',
            'religion_update_letter' => 'خطاب دعم تحديث بيانات الديانة',
            'other_letter' => 'خطاب عام',
        ];

        foreach ($names as $type => $name) {
            $code = 'NATIONAL-'.strtoupper($type);

            if (DocumentTemplate::query()->whereNull('tenant_id')->where('template_code', $code)->exists()) {
                continue;
            }

            $template = new DocumentTemplate;
            $template->template_code = $code;
            $template->document_type = LetterType::from($type);
            $template->name_ar = $name;
            $template->version = 1;
            $template->html_template = (string) file_get_contents(resource_path("document-templates/{$type}.html"));
            $template->orientation = 'portrait';
            $template->active = true;
            $template->approved_at = CarbonImmutable::now();
            $template->save();
        }
    }
}
