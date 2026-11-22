<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 09-data-model-guidance §9.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained('guidance_programs')->restrictOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('guidance_classes')->restrictOnDelete();
            $table->string('title');
            $table->string('exam_type', 20);
            $table->decimal('maximum_score', 6, 2);
            $table->decimal('passing_score', 6, 2);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('exam_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('component_code', 30);
            $table->string('name');
            $table->decimal('max_score', 6, 2);
            $table->decimal('weight', 5, 2);
            $table->timestamps();

            $table->unique(['exam_id', 'component_code']);
        });

        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('examiner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->timestamp('attempted_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->unique(['exam_id', 'enrollment_id', 'attempt_number']);
        });

        Schema::create('exam_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_component_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 6, 2);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_component_id']);
        });

        Schema::create('exam_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('final_score', 6, 2);
            $table->boolean('passed');
            $table->string('status', 20)->default('provisional');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->string('amendment_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['exam_results', 'exam_scores', 'exam_attempts', 'exam_components', 'exams'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
