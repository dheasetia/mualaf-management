<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11-data-model-platform §11.5 (export_jobs) and §11.8 (daily statistics).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_tenant_stats', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->date('stat_date');
            $table->string('metric', 60);
            $table->unsignedBigInteger('value');

            $table->primary(['tenant_id', 'stat_date', 'metric']);
            $table->index(['stat_date', 'metric']);
        });

        Schema::create('report_daily_national_stats', function (Blueprint $table): void {
            $table->date('stat_date');
            $table->string('metric', 60);
            $table->string('dimension', 60);
            $table->string('dimension_value', 60);
            $table->unsignedBigInteger('value');

            $table->primary(['stat_date', 'metric', 'dimension', 'dimension_value'], 'pk_report_daily_national_stats');
        });

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('export_type', 40);
            $table->json('filters');
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('storage_path', 500)->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['export_jobs', 'report_daily_national_stats', 'report_daily_tenant_stats'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
