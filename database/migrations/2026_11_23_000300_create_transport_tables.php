<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 08-data-model-dawah §8.7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('vehicle_number', 30);
            $table->string('plate_number', 20);
            $table->string('vehicle_type', 20);
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['tenant_id', 'plate_number']);
        });

        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('phone_encrypted');
            $table->text('license_number_encrypted')->nullable();
            $table->date('license_expires_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('activity_transport_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_plan_id')->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('transport_type', 20);
            $table->unsignedSmallInteger('vehicle_count')->default(1);
            $table->unsignedSmallInteger('passenger_count');
            $table->boolean('pickup_required')->default(false);
            $table->timestamp('departure_at');
            $table->timestamp('return_at')->nullable();
            $table->string('status', 20)->default('requested');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('transport_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_transport_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('departs_at');
            $table->timestamp('returns_at');
            $table->string('status', 20)->default('planned');
            $table->string('cancellation_reason', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'departs_at']);
            $table->index(['driver_id', 'departs_at']);
        });

        Schema::create('transport_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_transport_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('transport_trips')->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_plan_id')->nullable()->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->string('pickup_location_type', 20);
            $table->foreignId('residence_id')->nullable()->constrained('convert_residences')->restrictOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('pickup_address', 500)->nullable();
            $table->timestamp('pickup_at');
            $table->foreignId('contact_person_id')->nullable()->constrained('convert_contact_persons')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('disclosure_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'pickup_at']);
        });
    }

    public function down(): void
    {
        foreach (['transport_requests', 'transport_trips', 'activity_transport_requests', 'drivers', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
