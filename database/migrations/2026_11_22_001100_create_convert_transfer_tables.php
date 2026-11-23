<?php

declare(strict_types=1);

use App\Support\Database\AppendOnlyTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convert_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('to_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->timestamp('requested_at');
            $table->string('status', 20);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('new_primary_dai_id')->nullable()->constrained('dais')->restrictOnDelete();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalation_note', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // One open transfer per convert (critical test #11).
            $table->unsignedBigInteger('active_convert_key')
                ->nullable()
                ->storedAs("IF(status IN ('pending', 'under_review', 'approved', 'accepted', 'escalated'), convert_id, NULL)");
            $table->unique('active_convert_key', 'uq_convert_transfers_one_active');
            $table->index(['convert_id', 'status']);
            $table->index(['to_tenant_id', 'status']);
            $table->index(['from_tenant_id', 'status']);
        });

        Schema::create('convert_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_request_id')->constrained('convert_transfer_requests')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('metadata');
            $table->timestamp('created_at', 6);

            $table->index(['transfer_request_id', 'created_at']);
        });

        Schema::create('convert_transfer_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convert_id')->constrained()->restrictOnDelete();
            $table->foreignId('transfer_request_id')->unique()->constrained('convert_transfer_requests')->restrictOnDelete();
            $table->foreignId('from_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('to_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('previous_primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->foreignId('new_primary_dai_id')->constrained('dais')->restrictOnDelete();
            $table->timestamp('transferred_at');
            $table->string('reason', 1000);
            $table->timestamp('created_at', 6);

            $table->index(['convert_id', 'transferred_at']);
        });

        AppendOnlyTables::createTriggers('convert_transfer_events');
        AppendOnlyTables::createTriggers('convert_transfer_histories');
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Transfer tables are never dropped in production.');
        }

        AppendOnlyTables::dropTriggers('convert_transfer_histories');
        AppendOnlyTables::dropTriggers('convert_transfer_events');
        Schema::dropIfExists('convert_transfer_histories');
        Schema::dropIfExists('convert_transfer_events');
        Schema::dropIfExists('convert_transfer_requests');
    }
};
