<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Jobs\RecordAuditLog;
use App\Domain\Audit\Models\AuditLog;
use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Writes audit_logs (11-data-model-platform §11.1).
 *
 * record() inserts on the default connection, so inside DB::transaction() the
 * entry commits or rolls back with the operation it describes. recordLater()
 * is for read events (downloads, sensitive views): everything is captured now
 * and written by a job on the `audit` queue.
 */
final readonly class AuditLogger
{
    public const string QUEUE = 'audit';

    public function __construct(
        private AuditRedactor $redactor,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  int|null  $tenantId  defaults to the tenant in context
     * @param  User|null  $actor  defaults to the authenticated user
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null,
        ?User $actor = null,
        ?ActorType $actorType = null,
        ?int $privilegedGrantId = null,
    ): AuditLog {
        return $this->write($this->capture($action, $subject, $oldValues, $newValues, $tenantId, $actor, $actorType, $privilegedGrantId));
    }

    /**
     * Records the attributes changed by the subject's last save, old and new.
     */
    public function recordChanges(string $action, Model $subject, ?int $tenantId = null, ?User $actor = null): AuditLog
    {
        $changes = array_diff_key($subject->getChanges(), array_flip(['created_at', 'updated_at']));

        return $this->record(
            $action,
            $subject,
            oldValues: array_intersect_key($subject->getPrevious(), $changes),
            newValues: $changes,
            tenantId: $tenantId,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordLater(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null,
        ?User $actor = null,
        ?ActorType $actorType = null,
        ?int $privilegedGrantId = null,
    ): void {
        RecordAuditLog::dispatch($this->capture($action, $subject, $oldValues, $newValues, $tenantId, $actor, $actorType, $privilegedGrantId))->onQueue(self::QUEUE);
    }

    /**
     * @param  array<string, mixed>  $attributes  as returned by capture()
     */
    public function write(array $attributes): AuditLog
    {
        $log = new AuditLog;
        $log->forceFill($attributes);
        $log->save();

        return $log;
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array<string, mixed>
     */
    private function capture(
        string $action,
        ?Model $subject,
        array $oldValues,
        array $newValues,
        ?int $tenantId,
        ?User $actor,
        ?ActorType $actorType,
        ?int $privilegedGrantId,
    ): array {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;

        return [
            'created_at' => CarbonImmutable::now(),
            'tenant_id' => $tenantId ?? $this->tenantContext->id(),
            'actor_id' => $actor?->id,
            'actor_type' => $actorType ?? ($actor === null ? ActorType::System : ActorType::User),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values_redacted' => $oldValues === [] ? null : $this->redactor->redact($oldValues, $subject),
            'new_values_redacted' => $newValues === [] ? null : $this->redactor->redact($newValues, $subject),
            'privileged_grant_id' => $privilegedGrantId,
            'ip_address' => Context::getHidden(AssignRequestId::IP_KEY),
            'user_agent' => Context::getHidden(AssignRequestId::USER_AGENT_KEY),
            'request_id' => $this->requestId(),
        ];
    }

    /**
     * The id shared by every entry of one request, job or command.
     */
    private function requestId(): string
    {
        $id = Context::get(AssignRequestId::REQUEST_ID_KEY);

        if (! is_string($id)) {
            $id = (string) Str::uuid();
            Context::add(AssignRequestId::REQUEST_ID_KEY, $id);
        }

        return $id;
    }
}
