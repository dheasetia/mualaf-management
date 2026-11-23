<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\CertificateTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * National default templates, version 1, active. Seeded as approved by the
 * system (no user); later versions go through ManageTemplates approval.
 */
final class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([CertificateType::Shahadah, CertificateType::GuidanceCompletion] as $type) {
            $code = 'NATIONAL-'.strtoupper($type->value);

            if (CertificateTemplate::query()->whereNull('tenant_id')->where('template_code', $code)->exists()) {
                continue;
            }

            $template = new CertificateTemplate;
            $template->template_code = $code;
            $template->certificate_type = $type;
            $template->name = $type === CertificateType::Shahadah ? 'شهادة إشهار الإسلام' : 'شهادة إتمام برنامج التأهيل';
            $template->template_version = 1;
            $template->html_template = (string) file_get_contents(resource_path("certificate-templates/{$type->value}.html"));
            $template->configuration = [];
            $template->orientation = 'landscape';
            $template->active = true;
            $template->approved_at = CarbonImmutable::now();
            $template->save();
        }
    }
}
