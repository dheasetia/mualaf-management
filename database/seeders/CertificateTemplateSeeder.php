<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Models\CertificateTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * National default templates from resources/certificate-templates, seeded as
 * approved by the system (no user). Idempotent: when a file changes, the next
 * run adds it as a new version, active only while the active national version
 * is itself a system one, so a version approved by Central is never replaced.
 * Issued certificates keep the version they were issued with.
 */
final class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([CertificateType::Shahadah, CertificateType::GuidanceCompletion] as $type) {
            $this->seedType($type);
        }
    }

    private function seedType(CertificateType $type): void
    {
        $code = 'NATIONAL-'.strtoupper($type->value);
        $html = (string) file_get_contents(resource_path("certificate-templates/{$type->value}.html"));
        $versions = CertificateTemplate::query()->whereNull('tenant_id')->where('template_code', $code);

        $latestSystemVersion = (clone $versions)->whereNull('approved_by')->whereNotNull('approved_at')->orderByDesc('template_version')->first();

        if ($latestSystemVersion !== null && $latestSystemVersion->html_template === $html) {
            return;
        }

        $active = (clone $versions)->where('active', true)->first();
        $activate = $active === null || $active->approved_by === null;

        DB::transaction(function () use ($versions, $type, $code, $html, $activate): void {
            if ($activate) {
                (clone $versions)->update(['active' => false]);
            }

            $template = new CertificateTemplate;
            $template->template_code = $code;
            $template->certificate_type = $type;
            $template->name = $type === CertificateType::Shahadah ? 'شهادة إشهار الإسلام' : 'شهادة إتمام برنامج التأهيل';
            $template->template_version = 1 + (int) (clone $versions)->max('template_version');
            $template->html_template = $html;
            $template->configuration = [];
            $template->orientation = 'landscape';
            $template->active = $activate;
            $template->approved_at = CarbonImmutable::now();
            $template->save();
        });
    }
}
