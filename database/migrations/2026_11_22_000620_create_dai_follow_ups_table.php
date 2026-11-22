<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.7. material_id gets its foreign key with
 * dawah_materials (step 20); subjects are polymorphic (prospect, convert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dai_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dai_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('follow_up_type', 40);
            $table->string('contact_method', 40);
            $table->timestamp('occurred_at');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('location')->nullable();
            $table->text('summary');
            $table->text('questions_raised')->nullable();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('outcome')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('confidentiality_level', 20)->default('normal');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['dai_id', 'next_follow_up_at']);
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dai_follow_ups');
    }
};
