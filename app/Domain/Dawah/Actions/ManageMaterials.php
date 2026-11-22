<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dawah\Enums\MaterialStatus;
use App\Domain\Dawah\Enums\MaterialType;
use App\Domain\Dawah\Exceptions\DawahRuleViolation;
use App\Domain\Dawah\Models\DawahMaterial;
use App\Domain\Documents\Actions\Attachments;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Platform\Models\Language;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Office dawah materials (08 §8.4): drafted by materials.create, sent for
 * review, approved by materials.approve (not the author) or returned with a
 * note. Approved materials are not edited: a change is a new version, and
 * approving it retires the version it replaces. National materials are
 * read-only for offices.
 */
final readonly class ManageMaterials
{
    private const array FIELDS = ['title', 'title_ar', 'topic', 'target_audience', 'description', 'external_url', 'version'];

    public function __construct(private Attachments $attachments, private AuditLogger $audit, private TenantContext $tenantContext) {}

    /** @param  array<string, mixed>  $data */
    public function save(?DawahMaterial $material, array $data, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.create');

        if ($material !== null) {
            $this->requireOwnOffice($material);

            if ($material->status !== MaterialStatus::Draft) {
                throw DawahRuleViolation::notEditable();
            }
        }

        if (! Language::query()->whereKey((int) ($data['language_id'] ?? 0))->exists()) {
            throw DawahRuleViolation::languageUnavailable();
        }

        $material ??= new DawahMaterial;

        foreach (self::FIELDS as $field) {
            $material->setAttribute($field, blank($data[$field] ?? null) ? null : (string) $data[$field]);
        }

        $material->material_type = MaterialType::from((string) $data['material_type']);
        $material->language_id = (int) $data['language_id'];
        $material->stock_quantity = blank($data['stock_quantity'] ?? null) ? null : max(0, (int) $data['stock_quantity']);
        $material->version = (string) ($material->version ?? '1');

        if (! $material->exists) {
            $material->tenant_id = $this->tenantContext->requireId();
            $material->status = MaterialStatus::Draft;
            $material->created_by = $actor->id;
        }

        $material->save();

        return $material;
    }

    /** Attaches the material's file (PDF or image), replacing any earlier one while it is a draft. */
    public function attachFile(DawahMaterial $material, string $path, string $originalName, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.create');
        $this->requireOwnOffice($material);

        if ($material->status !== MaterialStatus::Draft) {
            throw DawahRuleViolation::notEditable();
        }

        $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
        $material->file_document_id = $this->attachments->store($material, DocumentType::DawahMaterial, "tenants/{$tenant->uuid}/dawah/materials/{$material->uuid}", $path, $originalName, $actor);
        $material->save();

        return $material;
    }

    public function downloadUrl(DawahMaterial $material, User $actor): string
    {
        $this->require($actor, 'materials.view');

        return $this->attachments->downloadUrl($material, $material->file_document_id ?? abort(404), $actor);
    }

    public function submitForReview(DawahMaterial $material, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.create');
        $this->requireOwnOffice($material);

        if ($material->file_document_id === null && blank($material->external_url)) {
            throw DawahRuleViolation::materialSourceRequired();
        }

        return $this->move($material, MaterialStatus::Draft, MaterialStatus::InReview, $actor, fn (DawahMaterial $locked) => $locked->review_note = null);
    }

    public function approve(DawahMaterial $material, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.approve');
        $this->requireOwnOffice($material);

        if ($material->created_by === $actor->id) {
            throw DawahRuleViolation::selfReview();
        }

        return $this->move($material, MaterialStatus::InReview, MaterialStatus::Approved, $actor, function (DawahMaterial $locked) use ($actor): void {
            $locked->approved_by = $actor->id;
            $locked->approved_at = CarbonImmutable::now();

            if ($locked->parent_material_id !== null) {
                DawahMaterial::query()->whereKey($locked->parent_material_id)->where('status', MaterialStatus::Approved)
                    ->update(['status' => MaterialStatus::Retired]);
            }
        });
    }

    public function returnToDraft(DawahMaterial $material, User $actor, ?string $note): DawahMaterial
    {
        $this->require($actor, 'materials.approve');
        $this->requireOwnOffice($material);
        $note = blank($note) ? throw DawahRuleViolation::reasonRequired() : trim((string) $note);

        return $this->move($material, MaterialStatus::InReview, MaterialStatus::Draft, $actor, fn (DawahMaterial $locked) => $locked->review_note = $note);
    }

    public function retire(DawahMaterial $material, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.approve');
        $this->requireOwnOffice($material);

        return $this->move($material, MaterialStatus::Approved, MaterialStatus::Retired, $actor);
    }

    /** A new draft version of an approved material, carrying over its details (not its file). */
    public function newVersion(DawahMaterial $material, string $version, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.create');
        $this->requireOwnOffice($material);

        if ($material->status !== MaterialStatus::Approved) {
            throw DawahRuleViolation::notEditable();
        }

        $draft = $this->save(null, [
            ...$material->only(['title', 'title_ar', 'topic', 'target_audience', 'description', 'external_url', 'language_id', 'stock_quantity']),
            'material_type' => $material->material_type->value,
        ], $actor);
        $draft->version = trim($version);
        $draft->parent_material_id = $material->id;
        $draft->save();

        return $draft;
    }

    /** Adds printed copies to an office material's stock. */
    public function addStock(DawahMaterial $material, int $quantity, User $actor): DawahMaterial
    {
        $this->require($actor, 'materials.create');
        $this->requireOwnOffice($material);

        if ($quantity <= 0) {
            throw DawahRuleViolation::invalidAmount();
        }

        return DB::transaction(function () use ($material, $quantity, $actor): DawahMaterial {
            $locked = DawahMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $locked->stock_quantity = (int) $locked->stock_quantity + $quantity;
            $locked->save();

            $this->audit->record('dawah_material.stock_added', $locked, newValues: ['quantity' => $quantity, 'stock' => $locked->stock_quantity], actor: $actor);

            return $locked;
        });
    }

    /** @param  (\Closure(DawahMaterial): mixed)|null  $apply */
    private function move(DawahMaterial $material, MaterialStatus $from, MaterialStatus $to, User $actor, ?\Closure $apply = null): DawahMaterial
    {
        return DB::transaction(function () use ($material, $from, $to, $actor, $apply): DawahMaterial {
            $locked = DawahMaterial::query()->lockForUpdate()->findOrFail($material->id);

            if ($locked->status !== $from) {
                throw DawahRuleViolation::invalidTransition($locked->status->label(), $to->label());
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $to;
            $locked->save();

            $this->audit->record('dawah_material.status_changed', $locked, ['status' => $from->value], ['status' => $to->value], actor: $actor);

            return $locked;
        });
    }

    private function requireOwnOffice(DawahMaterial $material): void
    {
        if ($material->tenant_id === null || $material->tenant_id !== $this->tenantContext->id()) {
            throw new AuthorizationException;
        }
    }

    private function require(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }
    }
}
