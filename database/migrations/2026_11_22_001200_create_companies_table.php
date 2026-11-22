<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06-data-model-core §6.6. tenant_id NULL = national directory (Central).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('registration_number', 40)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('name_ar');
            $table->index('city_id');
            $table->index('registration_number');
            $table->index(['tenant_id', 'name_ar']);
        });

        Schema::table('prospects', function (Blueprint $table): void {
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });
        Schema::dropIfExists('companies');
    }
};
