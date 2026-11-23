<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transfers\Actions\EscalateOverdueTransfers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transfers:escalate {--days= : SLA in days (default: config transfers.sla_days)}')]
#[Description('Escalate convert transfers the current office has not answered within the SLA')]
final class EscalateTransfers extends Command
{
    public function handle(EscalateOverdueTransfers $escalate): int
    {
        $days = (int) ($this->option('days') ?: config('transfers.sla_days'));
        $count = $escalate->handle($days);

        $this->info("{$count} transfer(s) escalated.");

        return self::SUCCESS;
    }
}
