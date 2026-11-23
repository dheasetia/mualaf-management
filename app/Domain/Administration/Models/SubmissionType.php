<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use Database\Factories\Administration\SubmissionTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * National catalogue of administrative submission types (10 §10.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $description
 * @property list<array{code: string, name_ar: string, name_en?: string, required: bool}> $required_documents
 * @property array{approval_stages?: int, sla_days?: int} $workflow_config
 * @property string|null $generates_document_template_code
 * @property bool $active
 */
#[UseFactory(SubmissionTypeFactory::class)]
final class SubmissionType extends Model
{
    /** @use HasFactory<SubmissionTypeFactory> */
    use HasFactory;

    protected $fillable = [];

    public function approvalStages(): int
    {
        return max(1, (int) ($this->workflow_config['approval_stages'] ?? 1));
    }

    public function slaDays(): ?int
    {
        $days = $this->workflow_config['sla_days'] ?? null;

        return $days === null ? null : (int) $days;
    }

    /** @return list<string> */
    public function requiredCodes(): array
    {
        return array_values(array_map(
            static fn (array $requirement): string => $requirement['code'],
            array_filter($this->required_documents, static fn (array $requirement): bool => $requirement['required']),
        ));
    }

    /** @return array<string, string> */
    public function requirementOptions(): array
    {
        $locale = app()->getLocale();

        return collect($this->required_documents)->mapWithKeys(fn (array $requirement): array => [
            $requirement['code'] => $locale === 'ar' ? $requirement['name_ar'] : ($requirement['name_en'] ?? $requirement['name_ar']),
        ])->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['required_documents' => 'array', 'workflow_config' => 'array', 'active' => 'boolean'];
    }
}
