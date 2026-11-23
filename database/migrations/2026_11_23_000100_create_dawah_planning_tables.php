<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 08-data-model-dawah §8.1, §8.2, §8.5, §8.6. Budget lines (§8.3) come with step 20.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dawah_activity_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('plan_number', 30);
            $table->string('title');
            $table->foreignId('activity_type_id')->constrained('dai_activity_types')->restrictOnDelete();
            $table->text('objective');
            $table->text('description')->nullable();
            $table->string('target_audience')->nullable();
            $table->unsignedInteger('expected_participants')->nullable();
            $table->foreignId('proposed_by_dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('responsible_dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('planned_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->decimal('approved_budget', 14, 2)->nullable();
            $table->decimal('actual_budget', 14, 2)->nullable();
            $table->boolean('transport_required')->default(false);
            $table->boolean('material_required')->default(false);
            $table->text('equipment_required')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->string('revision_note', 1000)->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'plan_number']);
            $table->index(['tenant_id', 'planned_date', 'status']);
        });

        Schema::create('dai_daily_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dai_id')->constrained('dais')->restrictOnDelete();
            $table->date('activity_date');
            $table->foreignId('activity_type_id')->constrained('dai_activity_types')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->time('started_at')->nullable();
            $table->time('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('related_prospect_id')->nullable()->constrained('prospects')->restrictOnDelete();
            $table->foreignId('related_convert_id')->nullable()->constrained('converts')->restrictOnDelete();
            $table->foreignId('related_activity_plan_id')->nullable()->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->decimal('output_quantity', 10, 2)->nullable();
            $table->string('output_unit', 30)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['dai_id', 'activity_date']);
            $table->index(['tenant_id', 'activity_date', 'status']);
        });

        Schema::create('activity_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_plan_id')->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->string('participant_type', 20);
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('participant_name')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('attendance_status', 20)->default('invited');
            $table->boolean('transportation_required')->default(false);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['activity_plan_id', 'participant_type']);
            $table->unique(['activity_plan_id', 'participant_type', 'participant_id'], 'uq_activity_participants_subject');
        });

        Schema::create('dawah_activity_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_plan_id')->unique()->constrained('dawah_activity_plans')->restrictOnDelete();
            $table->foreignId('dai_id')->constrained('dais')->restrictOnDelete();
            $table->unsignedInteger('actual_participants');
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->text('summary');
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('follow_up_actions')->nullable();
            $table->decimal('actual_budget', 14, 2)->nullable();
            $table->text('material_usage_summary')->nullable();
            $table->text('transport_summary')->nullable();
            $table->unsignedInteger('new_prospects_count')->default(0);
            $table->unsignedInteger('shahadah_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('return_note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dawah_activity_reports', 'activity_participants', 'dai_daily_activities', 'dawah_activity_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
