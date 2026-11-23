<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06-data-model-core §6.8, core columns. photo_document_id and
 * current_follow_up_status_id get their foreign keys with documents (step 18)
 * and convert_follow_up_statuses (step 24).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('converts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('origin_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('current_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('full_name');
            $table->string('name_ar')->nullable();
            $table->string('previous_name')->nullable();
            $table->string('islamic_name')->nullable();
            $table->string('name_search_key');
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->foreignId('nationality_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('native_language_id')->nullable()->constrained('languages')->restrictOnDelete();
            $table->foreignId('previous_religion_id')->nullable()->constrained('religions')->restrictOnDelete();
            $table->string('previous_religion_other')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('disclosure_risk_level', 40)->default('normal');
            $table->foreignId('primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('photo_document_id')->nullable();
            $table->unsignedBigInteger('current_follow_up_status_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['current_tenant_id', 'status']);
            $table->index(['origin_tenant_id', 'created_at']);
            $table->index(['primary_dai_id', 'status']);
            $table->index('name_search_key');
        });
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('converts is never dropped in production.');
        }

        Schema::dropIfExists('converts');
    }
};
