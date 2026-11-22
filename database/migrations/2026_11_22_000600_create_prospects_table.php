<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06-data-model-core §6.7. company_id and convert_id get their foreign keys
 * with the companies (step 14) and converts (step 10) tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('name_search_key');
            $table->string('gender', 10);
            $table->foreignId('nationality_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('native_language_id')->nullable()->constrained('languages')->restrictOnDelete();
            $table->foreignId('current_religion_id')->nullable()->constrained('religions')->restrictOnDelete();
            $table->string('current_religion_other')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->binary('phone_fingerprint', 32, fixed: true)->nullable();
            $table->text('email_encrypted')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('occupation')->nullable();
            $table->foreignId('residence_city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('residence_description')->nullable();
            $table->foreignId('primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->string('source_type', 40);
            $table->string('source_description')->nullable();
            $table->string('interest_level', 20)->nullable();
            $table->string('status', 40)->default('new');
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('convert_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['primary_dai_id', 'next_follow_up_at']);
            $table->index('phone_fingerprint');
            $table->index(['tenant_id', 'name_search_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
