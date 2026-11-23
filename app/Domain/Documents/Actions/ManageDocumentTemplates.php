<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Exceptions\DocumentRuleViolation;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Letter templates (documents.templates.manage, BR-017): drafts are edited
 * until a second person approves; approved versions are frozen and a change
 * is a new version. Approving activates it and retires older versions.
 */
final readonly class ManageDocumentTemplates
{
    public function __construct(private AuditLogger $audit, private TenantContext $tenantContext) {}

    /** @param  array{template_code: string, document_type: string, name_ar: string, description?: string|null, html_template: string, orientation: string}  $data */
    public function saveDraft(?DocumentTemplate $template, array $data, User $actor): DocumentTemplate
    {
        $this->authorize($actor);

        if ($template !== null && $template->isApproved()) {
            throw DocumentRuleViolation::templateNotEditable();
        }

        return DB::transaction(function () use ($template, $data, $actor): DocumentTemplate {
            $template ??= new DocumentTemplate;

            if (! $template->exists) {
                $template->tenant_id = $this->tenantContext->id();
                $template->version = 1 + (int) DocumentTemplate::query()
                    ->where('template_code', $data['template_code'])
                    ->where('tenant_id', $this->tenantContext->id())
                    ->max('version');
                $template->created_by = $actor->id;
            }

            $template->template_code = $data['template_code'];
            $template->document_type = LetterType::from($data['document_type']);
            $template->name_ar = $data['name_ar'];
            $template->description = $data['description'] ?? null;
            $template->html_template = $data['html_template'];
            $template->orientation = $data['orientation'];
            $template->save();

            return $template;
        });
    }

    public function newVersion(DocumentTemplate $template, User $actor): DocumentTemplate
    {
        return $this->saveDraft(null, [
            'template_code' => $template->template_code,
            'document_type' => $template->document_type->value,
            'name_ar' => $template->name_ar,
            'description' => $template->description,
            'html_template' => $template->html_template,
            'orientation' => $template->orientation,
        ], $actor);
    }

    public function approve(DocumentTemplate $template, User $actor): DocumentTemplate
    {
        $this->authorize($actor);

        if ($template->created_by === $actor->id) {
            throw DocumentRuleViolation::selfApproval();
        }

        return DB::transaction(function () use ($template, $actor): DocumentTemplate {
            DocumentTemplate::query()
                ->where('template_code', $template->template_code)
                ->where('tenant_id', $template->tenant_id)
                ->whereKeyNot($template->id)
                ->update(['active' => false]);

            $template->approved_by = $actor->id;
            $template->approved_at = CarbonImmutable::now();
            $template->active = true;
            $template->save();

            $this->audit->record('document_template.approved', newValues: [
                'template_id' => $template->id, 'code' => $template->template_code, 'version' => $template->version,
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
