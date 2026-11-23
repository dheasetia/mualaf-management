<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06-data-model-core §6.9: employment, residence and contact persons of a convert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_employments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('company_name_free')->nullable();
            $table->string('employee_number', 40)->nullable();
            $table->string('occupation')->nullable();
            $table->string('department')->nullable();
            $table->string('work_location_description')->nullable();
            $table->foreignId('work_city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->decimal('work_latitude', 10, 7)->nullable();
            $table->decimal('work_longitude', 10, 7)->nullable();
            $table->string('work_google_maps_url', 500)->nullable();
            $table->text('work_phone_encrypted')->nullable();
            $table->string('employment_status', 20)->default('employed');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['convert_id', 'is_current']);
            $table->index('company_id');
        });

        Schema::create('convert_residences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->string('residence_type', 30);
            $table->string('name')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('building_no', 20)->nullable();
            $table->string('room_no', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->boolean('is_pickup_point')->default(false);
            $table->string('pickup_instructions', 500)->nullable();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['convert_id', 'is_current']);
        });

        Schema::create('convert_contact_persons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('residence_id')->nullable()->constrained('convert_residences')->restrictOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('contact_role', 30);
            $table->text('phone_encrypted')->nullable();
            $table->text('alternate_phone_encrypted')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('preferred_language_id')->nullable()->constrained('languages')->restrictOnDelete();
            $table->boolean('permission_to_contact')->default(false);
            $table->string('disclosure_scope', 30)->default('none');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('convert_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convert_contact_persons');
        Schema::dropIfExists('convert_residences');
        Schema::dropIfExists('convert_employments');
    }
};
