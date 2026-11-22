<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.2. candidate_key (new in v1.1) identifies the
 * person being registered across attempts without storing their identity
 * number: a SHA-256 of the identity fingerprint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_review_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('candidate_type', 30);
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->char('candidate_key', 64);
            $table->foreignId('possible_convert_id')->constrained('converts')->restrictOnDelete();
            $table->string('match_reason', 30);
            $table->decimal('match_score', 4, 3)->nullable();
            $table->json('match_details');
            $table->string('status', 20)->default('open');
            $table->string('decision', 30)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['candidate_key', 'possible_convert_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_review_cases');
    }
};
