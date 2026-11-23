<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11-data-model-platform §11.1. No foreign keys and PK (id, created_at) so the
 * table can later be partitioned by month without a rebuild (05 §5.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', autoIncrement: true);
            $table->timestamp('created_at', 6);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 80);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values_redacted')->nullable();
            $table->json('new_values_redacted')->nullable();
            $table->unsignedBigInteger('privileged_grant_id')->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->string('user_agent')->nullable();
            $table->char('request_id', 36);

            $table->primary(['id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        AppendOnlyTables::createTriggers('audit_logs');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('audit_logs is never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('audit_logs');
        Schema::dropIfExists('audit_logs');
    }
};
