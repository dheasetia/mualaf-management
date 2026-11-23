<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10-data-model-administration §10.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('description')->nullable();
            $table->json('required_documents');
            $table->json('workflow_config');
            $table->string('generates_document_template_code', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('submission_type_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 30);
            $table->string('status', 30)->default('draft');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('correction_note', 1000)->nullable();
            $table->unsignedBigInteger('generated_document_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference_no']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('submission_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->string('requirement_code', 40);
            $table->string('verification_status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'requirement_code']);
        });

        Schema::create('submission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->json('metadata');
            $table->timestamp('created_at', 6);

            $table->index(['submission_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('submission_events');

        Schema::create('submission_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('approval_stage');
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->string('reason', 1000)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            // One decision per stage per submission cycle, enforced under lock by SubmissionWorkflow.
            $table->index(['submission_id', 'approval_stage']);
        });

        Schema::create('submission_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('assignment_type', 20);
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        AppendOnlyTables::dropTriggers('submission_events');

        foreach (['submission_assignments', 'submission_approvals', 'submission_events', 'submission_documents', 'submissions', 'submission_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
