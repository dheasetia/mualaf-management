<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11-data-model-platform §11.4, 04-identity-privacy §4.8 (break-glass).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privileged_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->text('reason');
            $table->json('requested_scope');
            $table->unsignedSmallInteger('requested_duration_minutes');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('privileged_grant_id')->references('id')->on('privileged_access_requests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['privileged_grant_id']);
        });
        Schema::dropIfExists('privileged_access_requests');
    }
};
