<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09-data-model-guidance §9.7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('owner_type', 40);
            $table->unsignedBigInteger('owner_id');
            $table->string('document_type', 60);
            $table->string('original_filename');
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum', 64);
            $table->unsignedInteger('current_version')->default(1);
            $table->string('verification_status', 20)->default('pending');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('storage_path', 500);
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6);

            $table->unique(['document_id', 'version_no']);
        });

        AppendOnlyTables::createTriggers('document_versions');

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('template_code', 40);
            $table->string('document_type', 40);
            $table->string('name_ar');
            $table->string('description')->nullable();
            $table->longText('html_template');
            $table->string('paper_size', 10)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->unsignedSmallInteger('version');
            $table->boolean('active')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'template_code', 'version'], 'uq_document_templates_version');
            $table->string('national_version_key', 60)->nullable()->storedAs("IF(tenant_id IS NULL, CONCAT(template_code, ':', version), NULL)");
            $table->unique('national_version_key', 'uq_document_templates_national_version');
        });

        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('document_templates')->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('document_number', 40)->unique();
            $table->json('snapshot_data');
            $table->unsignedBigInteger('file_document_id')->nullable();
            $table->char('file_checksum', 64)->nullable();
            $table->string('status', 20);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        AppendOnlyTables::dropTriggers('document_versions');

        foreach (['generated_documents', 'document_templates', 'document_versions', 'documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
