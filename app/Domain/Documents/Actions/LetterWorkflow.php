<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Models\Convert;
use App\Domain\Documents\Enums\GeneratedStatus;
use App\Domain\Documents\Enums\LetterType;
use App\Domain\Documents\Exceptions\DocumentRuleViolation;
use App\Domain\Documents\Jobs\GenerateLetterPdf;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Services\LetterSnapshotBuilder;
use App\Domain\Platform\Enums\Gender;
use App\Domain\Registration\Services\ShahadahDirectory;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Numbering\NumberAllocator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Letters for a convert (documents.generate): issued at once with a number,
 * the template version in force and a frozen snapshot, then rendered to
 * PDF. An issued letter never changes; a correction is a new letter and
 * the cancellation of the old one (09 §9.7).
 */
final readonly class LetterWorkflow
{
    /** Certificates are issued by the certificates module, not as letters. */
    public const array CONVERT_LETTERS = [
        LetterType::NewConvertDataLetter,
        LetterType::KhitanReferralLetter,
        LetterType::ReligionUpdateLetter,
        LetterType::OtherLetter,
    ];

    public function __construct(
        private LetterSnapshotBuilder $snapshots,
        private ShahadahDirectory $shahadah,
        private NumberAllocator $numbers,
        private AuditLogger $audit,
        private TenantContext $tenantContext,
    ) {}

    /** @param  array{addressee?: string|null, body?: string|null}  $input */
    public function generate(Convert $convert, LetterType $type, array $input, User $actor): GeneratedDocument
    {
        if (! $actor->can('generateLetters', $convert)) {
            throw new AuthorizationException;
        }

        if (! in_array($type, self::CONVERT_LETTERS, true)) {
            throw DocumentRuleViolation::letterNotForConvert();
        }

        if ($type === LetterType::KhitanReferralLetter && $convert->gender !== Gender::Male) {
            throw DocumentRuleViolation::khitanMaleOnly();
        }

        if ($this->shahadah->verifiedFirst($convert->id) === null) {
            throw DocumentRuleViolation::shahadahNotVerified();
        }

        $template = $this->template($type);

        $letter = DB::transaction(function () use ($convert, $type, $input, $actor, $template): GeneratedDocument {
            $tenant = Tenant::query()->findOrFail($this->tenantContext->requireId());
            $now = CarbonImmutable::now();
            $year = $now->setTimezone((string) config('app.display_timezone'))->format('Y');
            $number = NumberAllocator::format($tenant->code, 'DOC', $year, $this->numbers->next($tenant->id, 'generated_document', $year));

            $letter = new GeneratedDocument;
            $letter->template_id = $template->id;
            $letter->subject_type = $convert->getMorphClass();
            $letter->subject_id = $convert->id;
            $letter->document_number = $number;
            $letter->snapshot_data = $this->snapshots->build($number, $type, $convert, $tenant, $now, $input);
            $letter->status = GeneratedStatus::Issued;
            $letter->generated_by = $actor->id;
            $letter->issued_at = $now;
            $letter->save();

            $this->audit->record('document.generated', $convert, newValues: ['generated_document_id' => $letter->id, 'number' => $number, 'type' => $type->value], actor: $actor);

            return $letter;
        });

        GenerateLetterPdf::dispatch($letter->id, $letter->tenant_id)->onQueue(GenerateLetterPdf::QUEUE)->afterCommit();

        return $letter;
    }

    public function cancel(GeneratedDocument $letter, User $actor, ?string $reason): GeneratedDocument
    {
        $convert = $this->subject($letter);

        if (! $actor->can('generateLetters', $convert)) {
            throw new AuthorizationException;
        }

        if (blank($reason)) {
            throw DocumentRuleViolation::reasonRequired();
        }

        return DB::transaction(function () use ($letter, $convert, $actor, $reason): GeneratedDocument {
            $locked = GeneratedDocument::query()->lockForUpdate()->findOrFail($letter->id);

            if ($locked->status !== GeneratedStatus::Issued) {
                throw DocumentRuleViolation::notIssued();
            }

            $locked->status = GeneratedStatus::Cancelled;
            $locked->cancelled_by = $actor->id;
            $locked->cancellation_reason = trim((string) $reason);
            $locked->save();

            $this->audit->record('document.cancelled', $convert, newValues: ['generated_document_id' => $locked->id, 'reason' => $locked->cancellation_reason], actor: $actor);

            return $locked;
        });
    }

    public function downloadUrl(GeneratedDocument $letter, User $actor): string
    {
        $convert = $this->subject($letter);

        if (! $actor->can('viewDocuments', $convert) || ! $letter->hasPdf()) {
            throw new AuthorizationException;
        }

        $tenant = Tenant::query()->findOrFail($letter->tenant_id);
        $this->audit->record('document.downloaded', $convert, newValues: ['generated_document_id' => $letter->id], actor: $actor);

        return Storage::disk((string) config('documents.disk'))->temporaryUrl(
            $letter->pdfPath($tenant->uuid),
            CarbonImmutable::now()->addMinutes((int) config('documents.download_link_minutes')),
            ['ResponseContentDisposition' => 'attachment; filename="'.$letter->document_number.'.pdf"'],
        );
    }

    /** The office's active approved version of the type, else the national one. */
    private function template(LetterType $type): DocumentTemplate
    {
        return DocumentTemplate::query()
            ->where('document_type', $type)
            ->where('active', true)
            ->whereNotNull('approved_at')
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantContext->id()))
            ->orderByRaw('tenant_id IS NULL')
            ->orderByDesc('version')
            ->first() ?? throw DocumentRuleViolation::noTemplate();
    }

    private function subject(GeneratedDocument $letter): Convert
    {
        $convert = $letter->subject_type === (new Convert)->getMorphClass() ? Convert::query()->find($letter->subject_id) : null;

        return $convert ?? throw new AuthorizationException;
    }
}
