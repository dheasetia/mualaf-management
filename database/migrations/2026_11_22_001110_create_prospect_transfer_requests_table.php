<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 07-data-model-lifecycle §7.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('to_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('status', 20);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('new_primary_dai_id')->nullable()->constrained('dais')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unsignedBigInteger('active_prospect_key')
                ->nullable()
                ->storedAs("IF(status = 'pending', prospect_id, NULL)");
            $table->unique('active_prospect_key', 'uq_prospect_transfers_one_active');
            $table->index(['to_tenant_id', 'status']);
            $table->index(['from_tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_transfer_requests');
    }
};
