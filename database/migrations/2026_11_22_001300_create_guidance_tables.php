<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09-data-model-guidance §9.1–9.4. certificate_template_id gets its foreign
 * key with certificate_templates (step 17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days')->default(90);
            $table->decimal('minimum_attendance_percentage', 5, 2)->default(75);
            $table->decimal('passing_score', 5, 2)->default(60);
            $table->unsignedBigInteger('certificate_template_id')->nullable();
            $table->foreignId('based_on_program_id')->nullable()->constrained('guidance_programs')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // NULL tenant_id would not collide in a unique key: national codes are unique through national_code_key.
            $table->unique(['tenant_id', 'code']);
            $table->string('national_code_key', 30)->nullable()->storedAs('IF(tenant_id IS NULL, code, NULL)');
            $table->unique('national_code_key', 'uq_guidance_programs_national_code');
        });

        Schema::create('guidance_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('guidance_programs')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('weight', 5, 2)->default(1);
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->index(['program_id', 'sort_order']);
        });

        Schema::create('guidance_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained('guidance_modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['module_id', 'sort_order']);
        });

        Schema::create('guidance_classes', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained('guidance_programs')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->foreignId('language_id')->constrained()->restrictOnDelete();
            $table->string('gender_policy', 10);
            $table->unsignedSmallInteger('capacity');
            $table->string('room')->nullable();
            $table->string('location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('planned');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('class_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('guidance_classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('class_teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('guidance_classes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->string('active_teacher_key', 41)->nullable()->storedAs("IF(released_at IS NULL, CONCAT(class_id, ':', user_id), NULL)");
            $table->unique('active_teacher_key', 'uq_class_teachers_one_active');
            $table->index(['user_id', 'released_at']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('guidance_programs')->restrictOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('guidance_classes')->restrictOnDelete();
            $table->timestamp('enrolled_at');
            $table->date('target_completion_date');
            $table->string('status', 20)->default('enrolled');
            $table->timestamp('completed_at')->nullable();
            $table->string('withdrawn_reason', 500)->nullable();
            $table->foreignId('transferred_to_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->foreignId('enrolled_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->string('active_enrollment_key', 41)->nullable()
                ->storedAs("IF(status IN ('enrolled', 'active', 'paused'), CONCAT(convert_id, ':', program_id), NULL)");
            $table->unique('active_enrollment_key', 'uq_enrollments_one_active');
            $table->index(['service_tenant_id', 'status']);
            $table->index(['class_id', 'status']);
            $table->index('convert_id');
        });

        Schema::create('class_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('guidance_classes')->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('guidance_lessons')->restrictOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'starts_at']);
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'enrollment_id']);
        });

        Schema::create('learning_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('guidance_lessons')->restrictOnDelete();
            $table->string('progress_status', 20)->default('not_started');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('competency_notes')->nullable();
            $table->boolean('needs_mentoring')->default(false);
            $table->foreignId('assessed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->string('source', 20)->default('direct');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        foreach (['learning_progress', 'attendances', 'class_sessions', 'enrollments', 'class_teacher_assignments', 'class_schedules', 'guidance_classes', 'guidance_lessons', 'guidance_modules', 'guidance_programs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
