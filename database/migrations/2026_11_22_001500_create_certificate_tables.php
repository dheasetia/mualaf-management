<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09-data-model-guidance §9.6. file_document_id gets its foreign key with
 * documents (step 18); until then the PDF lives at a path derived from the
 * certificate uuid and file_checksum marks it as generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('template_code', 40);
            $table->string('certificate_type', 30);
            $table->string('name');
            $table->unsignedSmallInteger('template_version');
            $table->longText('html_template');
            $table->json('configuration');
            $table->string('paper_size', 10)->default('A4');
            $table->string('orientation', 20)->default('landscape');
            $table->boolean('active')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'template_code', 'template_version'], 'uq_certificate_templates_version');
            $table->string('national_version_key', 60)->nullable()->storedAs("IF(tenant_id IS NULL, CONCAT(template_code, ':', template_version), NULL)");
            $table->unique('national_version_key', 'uq_certificate_templates_national_version');
        });

        Schema::table('guidance_programs', function (Blueprint $table): void {
            $table->foreign('certificate_template_id')->references('id')->on('certificate_templates')->restrictOnDelete();
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('certificate_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('certificate_type', 30);
            $table->string('certificate_number', 40)->nullable()->unique();
            $table->binary('verification_token_hash', 32, fixed: true)->nullable()->unique();
            $table->json('snapshot_data')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('file_document_id')->nullable();
            $table->char('file_checksum', 64)->nullable();
            $table->string('status', 30);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('revocation_reason', 1000)->nullable();
            $table->foreignId('replaced_by_certificate_id')->nullable()->constrained('certificates')->restrictOnDelete();
            $table->timestamps();

            // One live certificate of a type per convert (and per enrollment for completions).
            $table->string('active_certificate_key', 60)->nullable()
                ->storedAs("IF(status IN ('draft', 'pending_approval', 'approved', 'issued'), CONCAT(certificate_type, ':', convert_id, ':', IFNULL(enrollment_id, 0)), NULL)");
            $table->unique('active_certificate_key', 'uq_certificates_one_active');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('certificate_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('metadata');
            $table->timestamp('created_at', 6);

            $table->index(['certificate_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('certificate_events');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Certificate tables are never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('certificate_events');
        Schema::dropIfExists('certificate_events');
        Schema::dropIfExists('certificates');
        Schema::table('guidance_programs', function (Blueprint $table): void {
            $table->dropForeign(['certificate_template_id']);
        });
        Schema::dropIfExists('certificate_templates');
    }
};
