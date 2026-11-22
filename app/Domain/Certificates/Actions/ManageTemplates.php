<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Certificates\Enums\CertificateType;
use App\Domain\Certificates\Exceptions\CertificateRuleViolation;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Certificate templates (documents.templates.manage, 13 §13.2): a draft
 * version is edited until approved; approval needs a second person; an
 * approved version is never edited, a change is a new version. Activating
 * a version deactivates the other versions of the same code.
 */
final readonly class ManageTemplates
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /** @param  array{template_code: string, certificate_type: string, name: string, html_template: string, orientation: string}  $data */
    public function saveDraft(?CertificateTemplate $template, array $data, User $actor): CertificateTemplate
    {
        $this->authorize($actor);

        if ($template !== null && $template->isApproved()) {
            throw CertificateRuleViolation::templateNotEditable();
        }

        return DB::transaction(function () use ($template, $data, $actor): CertificateTemplate {
            $template ??= new CertificateTemplate;

            if (! $template->exists) {
                $template->tenant_id = $this->tenantContext->id();
                $template->template_version = 1 + (int) CertificateTemplate::query()
                    ->where('template_code', $data['template_code'])
                    ->where('tenant_id', $this->tenantContext->id())
                    ->max('template_version');
                $template->created_by = $actor->id;
                $template->configuration = [];
            }

            $template->template_code = $data['template_code'];
            $template->certificate_type = CertificateType::from($data['certificate_type']);
            $template->name = $data['name'];
            $template->html_template = $data['html_template'];
            $template->orientation = $data['orientation'];
            $template->save();

            return $template;
        });
    }

    /** Starts the next version as an editable copy of an approved one. */
    public function newVersion(CertificateTemplate $template, User $actor): CertificateTemplate
    {
        return $this->saveDraft(null, [
            'template_code' => $template->template_code,
            'certificate_type' => $template->certificate_type->value,
            'name' => $template->name,
            'html_template' => $template->html_template,
            'orientation' => $template->orientation,
        ], $actor);
    }

    public function approve(CertificateTemplate $template, User $actor): CertificateTemplate
    {
        $this->authorize($actor);

        if ($template->created_by === $actor->id) {
            throw CertificateRuleViolation::selfApproval();
        }

        return DB::transaction(function () use ($template, $actor): CertificateTemplate {
            CertificateTemplate::query()
                ->where('template_code', $template->template_code)
                ->where('tenant_id', $template->tenant_id)
                ->whereKeyNot($template->id)
                ->update(['active' => false]);

            $template->approved_by = $actor->id;
            $template->approved_at = CarbonImmutable::now();
            $template->active = true;
            $template->save();

            $this->audit->record('certificate_template.approved', newValues: [
                'template_id' => $template->id, 'code' => $template->template_code, 'version' => $template->template_version,
            ], actor: $actor);

            return $template;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasPermission('documents.templates.manage')) {
            throw new AuthorizationException;
        }
    }
}
