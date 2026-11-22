<?php

declare(strict_types=1);

namespace App\Domain\Audit\Jobs;

use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes an audit entry captured earlier by AuditLogger::recordLater().
 */
final class RecordAuditLog implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public readonly array $attributes) {}

    public function handle(AuditLogger $logger): void
    {
        $logger->write($this->attributes);
    }
}
