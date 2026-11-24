<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11-data-model-platform §11.5: import of historical converts. The uploaded
 * file is not kept; each row is stored encrypted (source_data_encrypted)
 * and carries the office so it is tenant-scoped like its batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('import_type', 40);
            $table->string('original_filename', 255);
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('error_summary', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->text('source_data_encrypted');
            $table->string('status', 20)->default('pending');
            $table->string('error_message', 500)->nullable();
            $table->string('resulting_record_type', 60)->nullable();
            $table->unsignedBigInteger('resulting_record_id')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
