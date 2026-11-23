<?php

declare(strict_types=1);

namespace App\Domain\Examinations\Models;

use App\Domain\Examinations\Enums\ResultStatus;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Examinations\ExamResultFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A finalized result is immutable: it changes only through
 * ExamFinalization::amend (status amended, reason, audit).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $exam_attempt_id
 * @property string $final_score
 * @property bool $passed
 * @property ResultStatus $status
 * @property int|null $finalized_by
 * @property CarbonImmutable|null $finalized_at
 * @property string|null $amendment_reason
 */
#[UseFactory(ExamResultFactory::class)]
final class ExamResult extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExamResultFactory> */
    use HasFactory;

    protected $fillable = [];

    private static bool $amending = false;

    protected static function booted(): void
    {
        self::updating(function (self $result): void {
            if ($result->getOriginal('status') !== ResultStatus::Provisional && ! self::$amending) {
                throw new LogicException('A finalized exam result changes only through an amendment.');
            }
        });
    }

    /** Used by ExamFinalization::amend only. */
    public function applyAmendment(string $finalScore, bool $passed, string $reason): void
    {
        self::$amending = true;

        try {
            $this->final_score = $finalScore;
            $this->passed = $passed;
            $this->status = ResultStatus::Amended;
            $this->amendment_reason = $reason;
            $this->save();
        } finally {
            self::$amending = false;
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
            'passed' => 'boolean',
            'status' => ResultStatus::class,
            'finalized_at' => 'immutable_datetime',
        ];
    }
}
