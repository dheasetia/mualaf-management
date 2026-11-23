<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10-data-model-administration §10.3–10.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_follow_up_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->string('status', 40);
            $table->timestamp('last_contact_at')->nullable();
            $table->string('last_contact_method', 30)->nullable();
            $table->foreignId('last_known_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->string('last_known_city')->nullable();
            $table->string('islamic_activity_status', 20)->nullable();
            $table->string('community_connection_status', 20)->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('follow_up_priority', 10)->default('normal');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6);

            $table->index(['convert_id', 'created_at']);
        });

        AppendOnlyTables::createTriggers('convert_follow_up_statuses');

        Schema::table('converts', function (Blueprint $table): void {
            $table->foreign('current_follow_up_status_id')->references('id')->on('convert_follow_up_statuses')->restrictOnDelete();
        });

        Schema::create('follow_up_schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_ar');
            $table->string('trigger_event', 30);
            $table->json('offsets');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('convert_long_term_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('dai_id')->constrained('dais')->restrictOnDelete();
            $table->timestamp('follow_up_at');
            $table->string('contact_method', 30);
            $table->string('contacted_person_type', 20);
            $table->string('contact_result', 20);
            $table->foreignId('current_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->string('current_city')->nullable();
            $table->string('current_activity_status')->nullable();
            $table->string('mosque_or_center_name')->nullable();
            $table->string('local_dai_name')->nullable();
            $table->string('learning_status')->nullable();
            $table->text('support_needed')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('confidentiality_level', 20)->default('normal');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['convert_id', 'follow_up_at']);
        });

        Schema::create('convert_follow_up_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('follow_up_schedule_templates')->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('follow_up_type', 30);
            $table->string('priority', 10)->default('normal');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome', 30)->nullable();
            $table->foreignId('long_term_follow_up_id')->nullable()->constrained('convert_long_term_follow_ups')->restrictOnDelete();
            $table->timestamps();

            $table->index(['dai_id', 'scheduled_at', 'status']);
            $table->index(['convert_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('converts', function (Blueprint $table): void {
            $table->dropForeign(['current_follow_up_status_id']);
        });
        AppendOnlyTables::dropTriggers('convert_follow_up_statuses');

        foreach (['convert_follow_up_schedules', 'convert_long_term_follow_ups', 'follow_up_schedule_templates', 'convert_follow_up_statuses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
