<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10 §10.1: a submission past its SLA is escalated once to the approvers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('due_at');
            $table->index(['tenant_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'due_at']);
            $table->dropColumn('escalated_at');
        });
    }
};
