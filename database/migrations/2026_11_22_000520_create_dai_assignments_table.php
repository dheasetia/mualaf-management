<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 07-data-model-lifecycle §7.6. Written by DaiAssignmentService (step 8);
        // subjects are polymorphic (prospect, convert), so there is no FK on them.
        Schema::create('dai_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dai_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('assignment_type', 40);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One active primary da'i per subject (05-database-conventions §5.2).
            $table->string('active_primary_key', 62)
                ->nullable()
                ->storedAs("IF(is_primary AND ended_at IS NULL, CONCAT(subject_type, ':', subject_id), NULL)");
            $table->unique('active_primary_key', 'uq_dai_assignments_one_primary');
            $table->index(['dai_id', 'ended_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dai_assignments');
    }
};
