<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of a call to an outside service (11 §11.6): which
 * integration, the outcome and the provider's reference. Never secrets,
 * phone numbers or message text.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $integration_name
 * @property string $direction
 * @property string $endpoint_reference
 * @property string|null $request_reference
 * @property int|null $response_code
 * @property string $status
 * @property string|null $error_message
 * @property CarbonImmutable $created_at
 */
final class IntegrationLog extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public static function outbound(string $integration, string $endpoint, string $status, ?int $tenantId = null, ?int $responseCode = null, ?string $reference = null, ?string $error = null): self
    {
        $log = new self;
        $log->tenant_id = $tenantId;
        $log->integration_name = $integration;
        $log->direction = 'outbound';
        $log->endpoint_reference = $endpoint;
        $log->request_reference = $reference;
        $log->response_code = $responseCode;
        $log->status = $status;
        $log->error_message = $error === null ? null : mb_substr($error, 0, 500);
        $log->save();

        return $log;
    }
}
