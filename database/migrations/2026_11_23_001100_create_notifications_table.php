<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11-data-model-platform §11.3: Laravel's notifications table plus the
 * office it belongs to, and follow-up schedule markers for overdue alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::table('convert_follow_up_schedules', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('long_term_follow_up_id');
        });
    }

    public function down(): void
    {
        Schema::table('convert_follow_up_schedules', function (Blueprint $table): void {
            $table->dropColumn('escalated_at');
        });
        Schema::dropIfExists('notifications');
    }
};
