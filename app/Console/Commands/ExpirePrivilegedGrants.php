<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Privileged\Actions\PrivilegedAccess;
use Illuminate\Console\Command;

/**
 * 04 §4.8 step 5: ends break-glass grants past their expiry (every 5 minutes).
 */
final class ExpirePrivilegedGrants extends Command
{
    protected $signature = 'privileged:expire';

    protected $description = 'End privileged access grants that have expired';

    public function handle(PrivilegedAccess $access): int
    {
        $this->info($access->expire().' grant(s) expired.');

        return self::SUCCESS;
    }
}
